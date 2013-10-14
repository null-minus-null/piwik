<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik_Plugins
 * @package CorePluginsAdmin
 */
namespace Piwik\Plugins\CorePluginsAdmin;

use Piwik\Common;
use Piwik\Filechecks;
use Piwik\Filesystem;
use Piwik\Nonce;
use Piwik\Piwik;
use Piwik\Plugin;
use Piwik\Plugin\Manager;
use Piwik\Url;
use Piwik\View;

/**
 * @package CorePluginsAdmin
 */
class Controller extends Plugin\ControllerAdmin
{
    const UPDATE_NONCE = 'CorePluginsAdmin.updatePlugin';
    const INSTALL_NONCE = 'CorePluginsAdmin.installPlugin';
    const ACTIVATE_NONCE = 'CorePluginsAdmin.activatePlugin';
    const DEACTIVATE_NONCE = 'CorePluginsAdmin.deactivatePlugin';
    const UNINSTALL_NONCE = 'CorePluginsAdmin.uninstallPlugin';

    private $validSortMethods = array('popular', 'newest', 'alpha');
    private $defaultSortMethod = 'popular';

    private function createUpdateOrInstallView($template, $nonceName)
    {
        $pluginName = $this->initPluginModification($nonceName);

        $view = $this->configureView('@CorePluginsAdmin/' . $template);

        $view->plugin = array('name' => $pluginName);

        try {
            $pluginInstaller = new PluginInstaller($pluginName);
            $pluginInstaller->installOrUpdatePluginFromMarketplace();

        } catch (\Exception $e) {
            $view->errorMessage = $e->getMessage();
            return $view;
        }

        $marketplace = new Marketplace();
        $view->plugin = $marketplace->getPluginInfo($pluginName);

        return $view;
    }

    public function updatePlugin()
    {
        $view = $this->createUpdateOrInstallView('updatePlugin', static::UPDATE_NONCE);
        echo $view->render();
    }

    public function installPlugin()
    {
        $view = $this->createUpdateOrInstallView('installPlugin', static::INSTALL_NONCE);
        $view->nonce = Nonce::getNonce(static::ACTIVATE_NONCE);

        echo $view->render();
    }

    public function uploadPlugin()
    {
        Piwik::checkUserIsSuperUser();

        $nonce = Common::getRequestVar('nonce', null, 'string');

        if (!Nonce::verifyNonce(static::INSTALL_NONCE, $nonce)) {
            throw new \Exception(Piwik::translate('General_ExceptionNonceMismatch'));
        }

        Nonce::discardNonce(static::INSTALL_NONCE);

        if (empty($_FILES['pluginZip'])) {
            throw new \Exception('You did not specify a ZIP file.');
        }

        if (!empty($_FILES['pluginZip']['error'])) {
            throw new \Exception('Something went wrong during the plugin file upload. Please try again.');
        }

        $file = $_FILES['pluginZip']['tmp_name'];
        if (!file_exists($file)) {
            throw new \Exception('Something went wrong during the plugin file upload. Please try again.');
        }

        $view = $this->configureView('@CorePluginsAdmin/uploadPlugin');

        $pluginInstaller = new PluginInstaller('uploaded');
        $pluginMetadata = $pluginInstaller->installOrUpdatePluginFromFile($file);

        $view->nonce = Nonce::getNonce(static::ACTIVATE_NONCE);
        $view->plugin = array(
            'name'        => $pluginMetadata->name,
            'version'     => $pluginMetadata->version,
            'isTheme'     => !empty($pluginMetadata->theme),
            'isActivated' => \Piwik\Plugin\Manager::getInstance()->isPluginActivated($pluginMetadata->name)
        );

        echo $view->render();
    }

    public function pluginDetails()
    {
        $pluginName = Common::getRequestVar('pluginName', null, 'string');

        $view = $this->configureView('@CorePluginsAdmin/pluginDetails');

        try {
            $marketplace = new Marketplace();
            $view->plugin = $marketplace->getPluginInfo($pluginName);
        } catch (\Exception $e) {
            $view->errorMessage = $e->getMessage();
        }

        echo $view->render();
    }

    private function createBrowsePluginsOrThemesView($template, $themesOnly)
    {
        $query = Common::getRequestVar('query', '', 'string', $_POST);
        $sort = Common::getRequestVar('sort', $this->defaultSortMethod, 'string');

        if (!in_array($sort, $this->validSortMethods)) {
            $sort = $this->defaultSortMethod;
        }

        $view = $this->configureView('@CorePluginsAdmin/' . $template);

        $marketplace = new Marketplace();
        $view->plugins = $marketplace->searchPlugins($query, $sort, $themesOnly);

        $view->query = $query;
        $view->sort = $sort;
        $view->installNonce = Nonce::getNonce(static::INSTALL_NONCE);
        $view->updateNonce = Nonce::getNonce(static::UPDATE_NONCE);
        $view->isSuperUser = Piwik::isUserIsSuperUser();

        return $view;
    }

    public function browsePlugins()
    {
        $view = $this->createBrowsePluginsOrThemesView('browsePlugins', $themesOnly = false);
        echo $view->render();
    }

    public function browseThemes()
    {
        $view = $this->createBrowsePluginsOrThemesView('browseThemes', $themesOnly = true);
        echo $view->render();
    }

    function extend()
    {
        $view = $this->configureView('@CorePluginsAdmin/extend');
        $view->installNonce = Nonce::getNonce(static::INSTALL_NONCE);
        $view->isSuperUser = Piwik::isUserIsSuperUser();

        echo $view->render();
    }

    private function createPluginsOrThemesView($template, $themesOnly)
    {
        Piwik::checkUserIsSuperUser();

        $activated = Common::getRequestVar('activated', false, 'integer', $_GET);
        $pluginName = Common::getRequestVar('pluginName', '', 'string');

        $view = $this->configureView('@CorePluginsAdmin/' . $template);

        $view->activatedPluginName = '';
        if ($activated && $pluginName) {
            $view->activatedPluginName = $pluginName;
        }

        $view->updateNonce = Nonce::getNonce(static::UPDATE_NONCE);
        $view->activateNonce = Nonce::getNonce(static::ACTIVATE_NONCE);
        $view->uninstallNonce = Nonce::getNonce(static::UNINSTALL_NONCE);
        $view->deactivateNonce = Nonce::getNonce(static::DEACTIVATE_NONCE);
        $view->pluginsInfo = $this->getPluginsInfo($themesOnly);

        $users = \Piwik\Plugins\UsersManager\API::getInstance()->getUsers();
        $view->otherUsersCount = count($users) - 1;
        $view->themeEnabled = \Piwik\Plugin\Manager::getInstance()->getThemeEnabled()->getPluginName();

        $marketplace = new Marketplace();
        $view->pluginsHavingUpdate = $marketplace->getPluginsHavingUpdate($themesOnly);

        return $view;
    }

    function plugins()
    {
        $view = $this->createPluginsOrThemesView('plugins', $themesOnly = false);
        echo $view->render();
    }

    function themes()
    {
        $view = $this->createPluginsOrThemesView('themes', $themesOnly = true);
        echo $view->render();
    }

    protected function configureView($template)
    {
        Piwik::checkUserIsNotAnonymous();

        $view = new View($template);
        $this->setBasicVariablesView($view);
        $this->displayWarningIfConfigFileNotWritable($view);

        $view->errorMessage = '';

        return $view;
    }

    protected function getPluginsInfo($themesOnly = false)
    {
        $plugins = \Piwik\Plugin\Manager::getInstance()->returnLoadedPluginsInfo();

        foreach ($plugins as $pluginName => &$plugin) {
            if (!isset($plugin['info'])) {
                $description = '<strong><em>'
                    . Piwik::translate('CorePluginsAdmin_PluginNotCompatibleWith', array($pluginName, self::getPiwikVersion()))
                    . '</strong> <br/> '
                    . Piwik::translate('CorePluginsAdmin_PluginAskDevToUpdate')
                    . '</em>';
                $plugin['info'] = array(
                    'description' => $description,
                    'version'     => Piwik::translate('General_Unknown'),
                    'theme'       => false,
                );
            }
        }

        $pluginsFiltered = $this->keepPluginsOrThemes($themesOnly, $plugins);
        return $pluginsFiltered;
    }

    protected function keepPluginsOrThemes($themesOnly, $plugins)
    {
        $pluginsFiltered = array();
        foreach ($plugins as $name => $thisPlugin) {

            $isTheme = false;
            if (!empty($thisPlugin['info']['theme'])) {
                $isTheme = (bool)$thisPlugin['info']['theme'];
            }
            if (($themesOnly && $isTheme)
                || (!$themesOnly && !$isTheme)
            ) {
                $pluginsFiltered[$name] = $thisPlugin;
            }
        }
        return $pluginsFiltered;
    }

    public function deactivate($redirectAfter = true)
    {
        $pluginName = $this->initPluginModification(static::DEACTIVATE_NONCE);
        \Piwik\Plugin\Manager::getInstance()->deactivatePlugin($pluginName);
        $this->redirectAfterModification($redirectAfter);
    }

    protected function redirectAfterModification($redirectAfter)
    {
        if ($redirectAfter) {
            Url::redirectToReferrer();
        }
    }

    protected function initPluginModification($nonceName)
    {
        Piwik::checkUserIsSuperUser();

        $nonce = Common::getRequestVar('nonce', null, 'string');

        if (!Nonce::verifyNonce($nonceName, $nonce)) {
            throw new \Exception(Piwik::translate('General_ExceptionNonceMismatch'));
        }

        Nonce::discardNonce($nonceName);

        $pluginName = Common::getRequestVar('pluginName', null, 'string');
        return $pluginName;
    }

    public function activate($redirectAfter = true)
    {
        $pluginName = $this->initPluginModification(static::ACTIVATE_NONCE);

        \Piwik\Plugin\Manager::getInstance()->activatePlugin($pluginName);

        if ($redirectAfter) {
            $params = array('activated' => 1, 'pluginName' => $pluginName);
            $plugin = \Piwik\Plugin\Manager::getInstance()->loadPlugin($pluginName);

            $actionToRedirect = 'plugins';
            if ($plugin->isTheme()) {
                $actionToRedirect = 'themes';
            }

            $this->redirectToIndex('CorePluginsAdmin', $actionToRedirect, null, null, null, $params);
        }
    }

    public function uninstall($redirectAfter = true)
    {
        $pluginName = $this->initPluginModification(static::UNINSTALL_NONCE);

        $uninstalled = \Piwik\Plugin\Manager::getInstance()->uninstallPlugin($pluginName);

        if (!$uninstalled) {
            $path = Filesystem::getPathToPiwikRoot() . '/plugins/' . $pluginName . '/';
            $messagePermissions = Filechecks::getErrorMessageMissingPermissions($path);

            $messageIntro = Piwik::translate("Warning: \"%s\" could not be uninstalled. Piwik did not have enough permission to delete the files in $path. ",
                $pluginName);
            $exitMessage = $messageIntro . "<br/><br/>" . $messagePermissions;
            Piwik_ExitWithMessage($exitMessage, $optionalTrace = false, $optionalLinks = false, $optionalLinkBack = true);
        }

        $this->redirectAfterModification($redirectAfter);
    }

}
