# Installation automatisée des Nodes

## Configuration

Set the enrollment variables from `.env.example`. Production requires a random
`CENTRALCLOUD_ENROLLMENT_HASH_KEY`, an exact signed Agent manifest URL and its
Ed25519 public key. No Dashboard egress CIDR or private PKI is required for new
Nodes: each enrollment creates an independent Agent Bearer token, encrypted in
the `nodes` table with `APP_KEY`.

The normative Agent release public key is
`specs/agent-release-public-key.txt`; keep the Dashboard value synchronized
with it during an intentional key rotation.

The old CA settings and `NodeCertificateIssuer` remain available only to
migrate existing mTLS Nodes. Do not generate a CA for a new Bearer deployment.

## Interactive approval

Run the two-line bootstrap shown under **Admin → Nodes → Add**. Enter the
displayed code at `/admin/nodes/claim`, verify detected hardware and addresses,
then select exact Agent version, HTTPS endpoint, region and environment. Admin
2FA, infrastructure permission and password confirmation are required.

## Automatic mode

Generate the one-time token from the Add Node page. It is displayed once.
Cloud-init must write it as root mode `0600`, run with `--token-file`, and
delete it after exchange.

## Cleanup, revocation and rotation

`php artisan centralcloud:enrollments:cleanup` expires stale records.
`centralcloud:tick` runs cleanup and bounded validation retries, including on a
15-minute webcron. Revocation invalidates bootstrap access and disables
scheduling.

Revocation also deletes the Dashboard copy of the per-node Agent token.
Re-enrollment creates a new independent token. A future rotation action can use
the existing `agent_token_rotated_at` field without changing node identity.

`agent_unreachable` leaves the Node in `VALIDATING`. Check that the Node FQDN
resolves publicly, Traefik obtained a valid certificate on 443, the internal
9443 route is active, and the token hash file is readable by the Agent; then use
the retry action.
