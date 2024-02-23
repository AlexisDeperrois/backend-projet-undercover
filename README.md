# projet-04-undercover-back

## Mise en place de mercure

### Installation du hub

https://mercure.rocks/docs/

get started 
pour connaître le processeur, taper uname -m dans le terminal
et prendre Linux x86_64 (pas le legacy)
puis le décomprésser
aller dans le dossier mercure décompréssé ouvrir un terminal
executer la commande suivante

```bash
MERCURE_PUBLISHER_JWT_KEY='3ae97441bfbdb65009a2df959f3ad74c' \
MERCURE_SUBSCRIBER_JWT_KEY='3ae97441bfbdb65009a2df959f3ad74c' \
SERVER_NAME=:3000 \
./mercure run --config Caddyfile.dev
```

### dans le projet symfony

#### Installation du bundle

```bash
composer require mercure
```

#### Config du .env.local

ajouter le bloc de symfony du.env dans le.env.local et le configurer avec les bons ports

```php
###> symfony/mercure-bundle ###
# See https://symfony.com/doc/current/mercure.html#configuration
# The URL of the Mercure hub, used by the app to publish updates (can be a local URL)
MERCURE_URL=http://localhost:3000/.well-known/mercure
# The public URL of the Mercure hub, used by the browser to connect
MERCURE_PUBLIC_URL=https//localhost:3000/.well-known/mercure
# The secret used to sign the JWTs
MERCURE_JWT_SECRET="!ChangeThisMercureHubJWTSecretKey!"
###< symfony/mercure-bundle ###
```

#### Remplacer le contenu de framework.yaml par ce qui suit ( en théorie seul le morceau http_client devrait être à modifier)

```php
# see https://symfony.com/doc/current/reference/configuration/framework.html
framework:
    secret: "%env(APP_SECRET)%"
    #csrf_protection: true
    http_method_override: false

    # Enables session support. Note that the session will ONLY be started if you read or write from it.
    # Remove or comment this section to explicitly disable session support.
    session:
        handler_id: null
        cookie_secure: auto
        cookie_samesite: lax
        storage_factory_id: session.storage.factory.native

    http_client:
        default_options:
            verify_peer: false
    #esi: true
    #fragments: true
    php_errors:
        log: true

when@test:
    framework:
        test: true
        session:
            storage_factory_id: session.storage.factory.mock_file
```