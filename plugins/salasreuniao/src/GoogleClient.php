<?php

namespace GlpiPlugin\Salasreuniao;

use DateTimeInterface;
use GLPIKey;
use GuzzleHttp\Client;
use RuntimeException;

/**
 * Cliente mínimo da API do Google Agenda, autenticado por conta de serviço
 * (JWT assinado com a chave privada, sem bibliotecas externas).
 */
class GoogleClient
{
    private const SCOPE = 'https://www.googleapis.com/auth/calendar';

    private array $chave;

    private string $api;

    private string $conta_delegada;

    private ?Client $http = null;

    public function __construct()
    {
        $chave = self::lerChave(Settings::get('service_account_key'));
        if ($chave === null) {
            throw new RuntimeException('A integração com o Google Agenda ainda não foi configurada.');
        }
        $this->chave          = $chave;
        $this->api            = rtrim(Settings::get('api_url'), '/');
        $this->conta_delegada = trim(Settings::get('conta_delegada'));
    }

    public static function isConfigured(): bool
    {
        return self::lerChave(Settings::get('service_account_key')) !== null;
    }

    /**
     * Valida o JSON da chave da conta de serviço.
     */
    public static function lerChave(string $json): ?array
    {
        $chave = json_decode($json, true);
        if (
            !is_array($chave)
            || ($chave['type'] ?? '') !== 'service_account'
            || empty($chave['client_email'])
            || empty($chave['private_key'])
            || openssl_pkey_get_private($chave['private_key']) === false
        ) {
            return null;
        }
        $chave['token_uri'] = $chave['token_uri'] ?? 'https://oauth2.googleapis.com/token';
        return $chave;
    }

    public static function emailContaServico(): string
    {
        $chave = self::lerChave(Settings::get('service_account_key'));
        return $chave['client_email'] ?? '';
    }

    public function usaDelegacao(): bool
    {
        return $this->conta_delegada !== '';
    }

    /**
     * Eventos da agenda no período (ocorrências de eventos recorrentes já expandidas).
     *
     * @return array<int, array>
     */
    public function listarEventos(string $agenda, DateTimeInterface $de, DateTimeInterface $ate): array
    {
        $itens = [];
        $pagina = null;
        do {
            $query = [
                'timeMin'      => $de->format(DATE_RFC3339),
                'timeMax'      => $ate->format(DATE_RFC3339),
                'singleEvents' => 'true',
                'maxResults'   => 2500,
                'fields'       => 'nextPageToken,items(id,status,transparency,start,end)',
            ];
            if ($pagina !== null) {
                $query['pageToken'] = $pagina;
            }
            $resp   = $this->chamar('GET', '/calendars/' . rawurlencode($agenda) . '/events', ['query' => $query]);
            $itens  = array_merge($itens, $resp['items'] ?? []);
            $pagina = $resp['nextPageToken'] ?? null;
        } while ($pagina !== null);

        return $itens;
    }

    public function criarEvento(string $agenda, array $evento): array
    {
        return $this->chamar('POST', '/calendars/' . rawurlencode($agenda) . '/events', [
            'query' => ['sendUpdates' => $this->usaDelegacao() ? 'all' : 'none'],
            'json'  => $evento,
        ]);
    }

    public function alterarEvento(string $agenda, string $evento_id, array $evento): array
    {
        return $this->chamar('PATCH', '/calendars/' . rawurlencode($agenda) . '/events/' . rawurlencode($evento_id), [
            'query' => ['sendUpdates' => $this->usaDelegacao() ? 'all' : 'none'],
            'json'  => $evento,
        ]);
    }

    public function excluirEvento(string $agenda, string $evento_id): void
    {
        try {
            $this->chamar('DELETE', '/calendars/' . rawurlencode($agenda) . '/events/' . rawurlencode($evento_id), [
                'query' => ['sendUpdates' => $this->usaDelegacao() ? 'all' : 'none'],
            ]);
        } catch (GoogleException $e) {
            // Já apagado no Google: o objetivo foi atingido
            if (!in_array($e->getCode(), [404, 410], true)) {
                throw $e;
            }
        }
    }

    private function chamar(string $metodo, string $caminho, array $opcoes = []): array
    {
        $opcoes['headers']['Authorization'] = 'Bearer ' . $this->token();
        return $this->requisicao($metodo, $this->api . $caminho, $opcoes);
    }

    private function token(): string
    {
        /** @var \Psr\SimpleCache\CacheInterface $GLPI_CACHE */
        global $GLPI_CACHE;

        $cache_key = 'plugin_salasreuniao_token_' . md5($this->chave['client_email'] . '|' . $this->conta_delegada);
        $token     = $GLPI_CACHE->get($cache_key);
        if (is_string($token) && $token !== '') {
            return $token;
        }

        $agora  = time();
        $claims = [
            'iss'   => $this->chave['client_email'],
            'scope' => self::SCOPE,
            'aud'   => $this->chave['token_uri'],
            'iat'   => $agora,
            'exp'   => $agora + 3600,
        ];
        if ($this->usaDelegacao()) {
            $claims['sub'] = $this->conta_delegada;
        }

        $resp = $this->requisicao('POST', $this->chave['token_uri'], [
            'form_params' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $this->jwt($claims),
            ],
        ]);
        $token = (string) ($resp['access_token'] ?? '');
        if ($token === '') {
            throw new GoogleException('O Google não devolveu um token de acesso.');
        }
        $GLPI_CACHE->set($cache_key, $token, max(60, (int) ($resp['expires_in'] ?? 3600) - 300));
        return $token;
    }

    private function jwt(array $claims): string
    {
        $b64  = static fn (string $s): string => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $data = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])) . '.' . $b64(json_encode($claims));
        if (!openssl_sign($data, $assinatura, $this->chave['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new GoogleException('Não foi possível assinar com a chave da conta de serviço.');
        }
        return $data . '.' . $b64($assinatura);
    }

    private function requisicao(string $metodo, string $url, array $opcoes): array
    {
        $opcoes['http_errors'] = false;
        $resp  = $this->http()->request($metodo, $url, $opcoes);
        $code  = $resp->getStatusCode();
        $corpo = (string) $resp->getBody();
        $dados = $corpo === '' ? [] : (json_decode($corpo, true) ?? []);

        if ($code >= 400) {
            $erro = $dados['error'] ?? null;
            $msg  = is_array($erro) ? ($erro['message'] ?? '') : ($dados['error_description'] ?? (is_string($erro) ? $erro : ''));
            throw new GoogleException(self::traduzirErro($code, (string) $msg), $code);
        }
        return $dados;
    }

    private static function traduzirErro(int $code, string $msg): string
    {
        return match (true) {
            $code === 404 => 'agenda não encontrada ou não compartilhada com a conta de serviço',
            $code === 403 => 'sem permissão na agenda (compartilhe com a conta de serviço com "Fazer alterações nos eventos")'
                . ($msg !== '' ? ' — ' . $msg : ''),
            $code === 401 => 'a chave da conta de serviço foi recusada pelo Google',
            default       => $msg !== '' ? $msg : 'erro HTTP ' . $code,
        };
    }

    private function http(): Client
    {
        global $CFG_GLPI;

        if ($this->http === null) {
            $config = ['timeout' => 20, 'connect_timeout' => 10];
            if (!empty($CFG_GLPI['proxy_name'])) {
                $auth = '';
                if (!empty($CFG_GLPI['proxy_user'])) {
                    $senha = (string) (new GLPIKey())->decrypt($CFG_GLPI['proxy_passwd'] ?? null);
                    $auth  = rawurlencode($CFG_GLPI['proxy_user']) . ':' . rawurlencode($senha) . '@';
                }
                $config['proxy'] = sprintf('http://%s%s:%d', $auth, $CFG_GLPI['proxy_name'], (int) $CFG_GLPI['proxy_port']);
            }
            $this->http = new Client($config);
        }
        return $this->http;
    }
}
