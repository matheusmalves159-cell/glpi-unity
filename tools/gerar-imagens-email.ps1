Add-Type -AssemblyName System.Drawing

$saida = Join-Path $PSScriptRoot '..\imagens-email'
New-Item -ItemType Directory -Force $saida | Out-Null

$W = 1670; $H = 216; $d = 124; $cy = 78
$rotulos = @('Aberto', 'Atendimento', 'Atualizado', 'Solucionado', 'Fechado')

function Cor($hex) { [System.Drawing.ColorTranslator]::FromHtml($hex) }

function Nova-Imagem($nome, $passos, $ativo, $corAtiva, $corNumeroAtivo) {
    $bmp = New-Object System.Drawing.Bitmap $W, $H
    $g = [System.Drawing.Graphics]::FromImage($bmp)
    $g.SmoothingMode = 'AntiAlias'
    $g.TextRenderingHint = 'AntiAliasGridFit'
    $g.InterpolationMode = 'HighQualityBicubic'

    # fundo escuro, igual ao dos e-mails atuais
    $fundo = New-Object System.Drawing.Drawing2D.LinearGradientBrush ([System.Drawing.Point]::new(0, 0)), ([System.Drawing.Point]::new(0, $H)), (Cor '#04101f'), (Cor '#081a30')
    $g.FillRectangle($fundo, 0, 0, $W, $H)

    $passo = ($W - 2 * 100) / 4.0
    $fNum = New-Object System.Drawing.Font 'Arial', 54, ([System.Drawing.FontStyle]::Bold), 'Pixel'
    $fRot = New-Object System.Drawing.Font 'Arial', 36, ([System.Drawing.FontStyle]::Bold), 'Pixel'
    $sf = New-Object System.Drawing.StringFormat
    $sf.Alignment = 'Center'; $sf.LineAlignment = 'Center'
    $laranja = New-Object System.Drawing.Pen (Cor '#f26b21'), 7
    $setaBrush = New-Object System.Drawing.SolidBrush (Cor '#f26b21')
    $branco = [System.Drawing.Brushes]::White

    for ($i = 0; $i -lt 5; $i++) {
        $x = 100 + $i * $passo
        $esq = $x - $d / 2

        # setas entre os círculos
        if ($i -lt 4) {
            $ini = $x + $d / 2 + 26
            $fim = $x + $passo - $d / 2 - 38
            $g.DrawLine($laranja, [single]$ini, [single]$cy, [single]$fim, [single]$cy)
            $pts = @(
                [System.Drawing.PointF]::new([single]($fim + 30), [single]$cy),
                [System.Drawing.PointF]::new([single]($fim - 6), [single]($cy - 17)),
                [System.Drawing.PointF]::new([single]($fim - 6), [single]($cy + 17))
            )
            $g.FillPolygon($setaBrush, $pts)
        }

        $rect = New-Object System.Drawing.RectangleF ([single]$esq), ([single]($cy - $d / 2)), ([single]$d), ([single]$d)
        if (($i + 1) -eq $ativo) {
            # brilho suave em volta do passo atual
            for ($r = 22; $r -ge 4; $r -= 6) {
                $alfa = [int](10 + (22 - $r) * 1.4)
                $b = New-Object System.Drawing.SolidBrush ([System.Drawing.Color]::FromArgb($alfa, (Cor $corAtiva)))
                $g.FillEllipse($b, $rect.X - $r, $rect.Y - $r, $rect.Width + 2 * $r, $rect.Height + 2 * $r)
            }
            $grad = New-Object System.Drawing.Drawing2D.LinearGradientBrush $rect, (Cor $corAtiva), (Cor $corAtiva), 90
            $g.FillEllipse($grad, $rect)
            $numCor = New-Object System.Drawing.SolidBrush (Cor $corNumeroAtivo)
        } else {
            $g.FillEllipse((New-Object System.Drawing.SolidBrush (Cor '#cfe4fb')), $rect)
            $numCor = New-Object System.Drawing.SolidBrush (Cor '#0b1f3a')
        }
        $g.DrawString([string]($i + 1), $fNum, $numCor, $rect, $sf)

        $rr = New-Object System.Drawing.RectangleF ([single]($x - $passo / 2 + 10)), 150, ([single]($passo - 20)), 56
        $g.DrawString($passos[$i], $fRot, $branco, $rr, $sf)
    }

    $arq = Join-Path $saida ($nome + '.png')
    $bmp.Save($arq, [System.Drawing.Imaging.ImageFormat]::Png)
    $g.Dispose(); $bmp.Dispose()
    Write-Output $arq
}

Nova-Imagem 'Pendente'  @('Aberto', 'Atendimento', 'Pendente', 'Solucionado', 'Fechado') 3 '#1f5fd0' '#ffffff'
Nova-Imagem 'Reprovado' @('Aberto', 'Atendimento', 'Atualizado', 'Reprovado', 'Fechado') 4 '#1f5fd0' '#ffffff'
Nova-Imagem 'Prazo'     @('Aberto', 'Atendimento', 'Prazo', 'Solucionado', 'Fechado') 3 '#f5b700' '#0b1f3a'

