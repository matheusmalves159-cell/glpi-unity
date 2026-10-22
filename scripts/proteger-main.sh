#!/bin/bash
# Protege a branch main no GitHub: exige Pull Request e verificações; ativa verificação de segredos.
# Precisa do GitHub CLI autenticado (gh auth login). Uso: ./scripts/proteger-main.sh DONO/REPO
set -euo pipefail
REPO="${1:?Informe DONO/REPO, ex.: unity/glpi-unity}"
gh api -X PUT "repos/$REPO/branches/main/protection" --input - <<JSON
{
  "required_status_checks": {"strict": true, "contexts": ["gitleaks", "php-sintaxe"]},
  "enforce_admins": false,
  "required_pull_request_reviews": {"required_approving_review_count": 0, "dismiss_stale_reviews": true},
  "restrictions": null,
  "required_linear_history": false,
  "allow_force_pushes": false,
  "allow_deletions": false
}
JSON
gh api -X PATCH "repos/$REPO" --input - <<JSON
{"security_and_analysis": {"secret_scanning": {"status": "enabled"}, "secret_scanning_push_protection": {"status": "enabled"}}}
JSON
echo "main protegida em $REPO"
