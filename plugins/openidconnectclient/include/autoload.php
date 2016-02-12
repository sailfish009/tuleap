<?php
// @codingStandardsIgnoreFile
// @codeCoverageIgnoreStart
// this is an autogenerated file - do not edit
function autoload4fad9e1a673d5ea4d812585fdafa3c54($class) {
    static $classes = null;
    if ($classes === null) {
        $classes = array(
            'openidconnectclientplugin' => '/openidconnectclientPlugin.class.php',
            'openidconnectclientplugindescriptor' => '/OpenIDConnectClientPluginDescriptor.class.php',
            'openidconnectclientplugininfo' => '/OpenIDConnectClientPluginInfo.class.php',
            'tuleap\\openidconnectclient\\authentication\\authorizationdispatcher' => '/OpenIDConnectClient/Authentication/AuthorizationDispatcher.php',
            'tuleap\\openidconnectclient\\authentication\\flow' => '/OpenIDConnectClient/Authentication/Flow.php',
            'tuleap\\openidconnectclient\\authentication\\flowresponse' => '/OpenIDConnectClient/Authentication/FlowResponse.php',
            'tuleap\\openidconnectclient\\authentication\\state' => '/OpenIDConnectClient/Authentication/State.php',
            'tuleap\\openidconnectclient\\authentication\\statefactory' => '/OpenIDConnectClient/Authentication/StateFactory.php',
            'tuleap\\openidconnectclient\\authentication\\statemanager' => '/OpenIDConnectClient/Authentication/StateManager.php',
            'tuleap\\openidconnectclient\\authentication\\statestorage' => '/OpenIDConnectClient/Authentication/StateStorage.php',
            'tuleap\\openidconnectclient\\logincontroller' => '/OpenIDConnectClient/LoginController.php',
            'tuleap\\openidconnectclient\\provider\\provider' => '/OpenIDConnectClient/Provider/Provider.php',
            'tuleap\\openidconnectclient\\provider\\providerdao' => '/OpenIDConnectClient/Provider/ProviderDao.php',
            'tuleap\\openidconnectclient\\provider\\providermanager' => '/OpenIDConnectClient/Provider/ProviderManager.php',
            'tuleap\\openidconnectclient\\provider\\providernotfoundexception' => '/OpenIDConnectClient/Provider/ProviderNotFoundException.php',
            'tuleap\\openidconnectclient\\router' => '/OpenIDConnectClient/Router.php',
            'tuleap\\openidconnectclient\\usermapping\\usermapping' => '/OpenIDConnectClient/UserMapping/UserMapping.php',
            'tuleap\\openidconnectclient\\usermapping\\usermappingdao' => '/OpenIDConnectClient/UserMapping/UserMappingDao.class.php',
            'tuleap\\openidconnectclient\\usermapping\\usermappingmanager' => '/OpenIDConnectClient/UserMapping/UserMappingManager.php',
            'tuleap\\openidconnectclient\\usermapping\\usermappingnotfoundexception' => '/OpenIDConnectClient/UserMapping/UserMappingNotFoundException.php'
        );
    }
    $cn = strtolower($class);
    if (isset($classes[$cn])) {
        require dirname(__FILE__) . $classes[$cn];
    }
}
spl_autoload_register('autoload4fad9e1a673d5ea4d812585fdafa3c54');
// @codeCoverageIgnoreEnd
