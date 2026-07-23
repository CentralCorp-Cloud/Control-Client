# Installation automatisée des Nodes

## Configuration

Set the enrollment variables from `.env.example`. Production requires a random
`CENTRALCLOUD_ENROLLMENT_HASH_KEY`, an exact signed Agent manifest URL and its
Ed25519 public key, Control Plane source CIDRs, and separate Node server CA and
Control Plane client CA files.

The normative Agent release public key is
`specs/agent-release-public-key.txt`; keep the Dashboard value synchronized
with it during an intentional key rotation.

For a development CA:

```sh
umask 077
openssl ecparam -name prime256v1 -genkey -noout -out node-ca.key
openssl req -x509 -new -key node-ca.key -sha256 -days 3650 \
  -subj "/CN=CentralCloud development Node CA" -out node-ca.crt
chmod 0600 node-ca.key
```

The PHP process must own the CA private key. Never store it in the database or
repository. Production should use an external signer implementing
`NodeCertificateIssuer`.

## Interactive approval

Run the two-line bootstrap shown under **Admin → Nodes → Add**. Enter the
displayed code at `/admin/nodes/claim`, verify detected hardware and addresses,
then select exact Agent version, endpoint, region, environment and Control
Plane CIDRs. Admin 2FA, infrastructure permission and password confirmation are
required.

## Automatic mode

Generate the one-time token from the Add Node page. It is displayed once.
Cloud-init must write it as root mode `0600`, run with `--token-file`, and
delete it after exchange.

## Cleanup, revocation and rotation

`php artisan centralcloud:enrollments:cleanup` expires stale records.
`centralcloud:tick` runs cleanup and bounded validation retries, including on a
15-minute webcron. Revocation invalidates bootstrap access and disables
scheduling.

Initial certificates are valid for `CENTRALCLOUD_NODE_CERTIFICATE_DAYS`.
Rotation uses a new locally generated CSR and the same validation rules; until
an automated rotation endpoint is introduced, perform rotation during a
maintenance window and keep the previous certificate for rollback.

`agent_unreachable` leaves the Node in `VALIDATING`. Check DNS, port 9443,
Control Plane egress CIDRs, firewall rules, client certificate SAN and both CA
chains, then use the retry action.
