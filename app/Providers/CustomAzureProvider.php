<?php

namespace App\Providers;


use SocialiteProviders\Azure\Provider as AzureProvider;

class CustomAzureProvider extends AzureProvider
{
    protected $tenantId;

    public function __construct($request, $clientId, $clientSecret, $redirectUrl, $tenantId)
    {
        parent::__construct($request, $clientId, $clientSecret, $redirectUrl);
        $this->tenantId = $tenantId;
    }

    protected function getTokenUrl()
    {
        return "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token";
    }



}
