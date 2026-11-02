<?php

namespace App\Providers;

use SocialiteProviders\Keycloak\Provider as SocialiteKeycloakProvider;

class KeycloakProvider extends SocialiteKeycloakProvider
{
    public function getAccessTokenResponse($code)
    {
        $response = parent::getAccessTokenResponse($code);
        $idToken = $response['id_token'] ?? null;

        if (is_string($idToken)) {
            $this->request->session()->put('keycloak.oidc.pending_id_token', $idToken);
        }

        return $response;
    }

    protected function getTokenFields($code)
    {
        $fields = parent::getTokenFields($code);

        if (empty($fields['client_secret'])) {
            unset($fields['client_secret']);
        }

        return $fields;
    }
}
