<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik
 * @package Piwik
 */

namespace Piwik\API;

use Exception;
use Piwik\Common;
use Piwik\Piwik;
use Piwik\Singleton;
use ReflectionClass;
use ReflectionMethod;

/**
 * Proxy is a singleton that has the knowledge of every method available, their parameters
 * and default values.
 * Proxy receives all the API calls requests via call() and forwards them to the right
 * object, with the parameters in the right order.
 *
 * It will also log the performance of API calls (time spent, parameter values, etc.) if logger available
 *
 * @package Piwik
 * @subpackage Piwik_API
 */
class Proxy extends Singleton
{
    // array of already registered plugins names
    protected $alreadyRegistered = array();

    private $metadataArray = array();
    private $hideIgnoredFunctions = true;

    // when a parameter doesn't have a default value we use this
    private $noDefaultValue;

    /**
     * protected constructor
     */
    protected function __construct()
    {
        $this->noDefaultValue = new NoDefaultValue();
    }

    /**
     * Returns array containing reflection meta data for all the loaded classes
     * eg. number of parameters, method names, etc.
     *
     * @return array
     */
    public function getMetadata()
    {
        ksort($this->metadataArray);
        return $this->metadataArray;
    }

    /**
     * Registers the API information of a given module.
     *
     * The module to be registered must be
     * - a singleton (providing a getInstance() method)
     * - the API file must be located in plugins/ModuleName/API.php
     *   for example plugins/Referrers/API.php
     *
     * The method will introspect the methods, their parameters, etc.
     *
     * @param string $className ModuleName eg. "API"
     */
    public function registerClass($className)
    {
        if (isset($this->alreadyRegistered[$className])) {
            return;
        }
        $this->includeApiFile($className);
        $this->checkClassIsSingleton($className);

        $rClass = new ReflectionClass($className);
        foreach ($rClass->getMethods() as $method) {
            $this->loadMethodMetadata($className, $method);
        }

        $this->setDocumentation($rClass, $className);
        $this->alreadyRegistered[$className] = true;
    }

    /**
     * Will be displayed in the API page
     *
     * @param ReflectionClass $rClass Instance of ReflectionClass
     * @param string $className Name of the class
     */
    private function setDocumentation($rClass, $className)
    {
        // Doc comment
        $doc = $rClass->getDocComment();
        $doc = str_replace(" * " . PHP_EOL, "<br>", $doc);

        // boldify the first line only if there is more than one line, otherwise too much bold
        if (substr_count($doc, '<br>') > 1) {
            $firstLineBreak = strpos($doc, "<br>");
            $doc = "<div class='apiFirstLine'>" . substr($doc, 0, $firstLineBreak) . "</div>" . substr($doc, $firstLineBreak + strlen("<br>"));
        }
        $doc = preg_replace("/(@package)[a-z _A-Z]*/", "", $doc);
        $doc = str_replace(array("\t", "\n", "/**", "*/", " * ", " *", "  ", "\t*", "  *  @package"), " ", $doc);
        $this->metadataArray[$className]['__documentation'] = $doc;
    }

    /**
     * Returns number of classes already loaded
     * @return int
     */
    public function getCountRegisteredClasses()
    {
        return count($this->alreadyRegistered);
    }

    /**
     * Will execute $className->$methodName($parametersValues)
     * If any error is detected (wrong number of parameters, method not found, class not found, etc.)
     * it will throw an exception
     *
     * It also logs the API calls, with the parameters values, the returned value, the performance, etc.
     * You can enable logging in config/global.ini.php (log_api_call)
     *
     * @param string $className The class name (eg. API)
     * @param string $methodName The method name
     * @param array $parametersRequest The parameters pairs (name=>value)
     *
     * @return mixed|null
     * @throws Exception|\Piwik\NoAccessException
     */
    public function call($className, $methodName, $parametersRequest)
    {
        $returnedValue = null;

        // Temporarily sets the Request array to this API call context
        $saveGET = $_GET;
        $saveQUERY_STRING = @$_SERVER['QUERY_STRING'];
        foreach ($parametersRequest as $param => $value) {
            $_GET[$param] = $value;
        }

        try {
            $this->registerClass($className);

            // instanciate the object
            $object = call_user_func(array($className, "getInstance"));

            // check method exists
            $this->checkMethodExists($className, $methodName);

            // get the list of parameters required by the method
            $parameterNamesDefaultValues = $this->getParametersList($className, $methodName);

            // load parameters in the right order, etc.
            $finalParameters = $this->getRequestParametersArray($parameterNamesDefaultValues, $parametersRequest);

            // allow plugins to manipulate the value
            $pluginName = $this->getModuleNameFromClassName($className);

            /**
             * Generic hook that plugins can use to modify any input to any API method. You could also use this to build
             * an enhanced permission system. The event is triggered shortly before any API method is executed.
             *
             * The `$fnalParameters` contains all paramteres that will be passed to the actual API method.
             */
            Piwik::postEvent(sprintf('API.Request.dispatch', $pluginName, $methodName), array(&$finalParameters));

            /**
             * This event is similar to the `API.Request.dispatch` event. It distinguishes the possibility to subscribe
             * only to a specific API method instead of all API methods. You can use it for example to modify any input
             * parameters or to execute any other logic before the actual API method is called.
             */
            Piwik::postEvent(sprintf('API.%s.%s', $pluginName, $methodName), array(&$finalParameters));

            // call the method
            $returnedValue = call_user_func_array(array($object, $methodName), $finalParameters);

            $endHookParams = array(
                &$returnedValue,
                array('className'  => $className,
                      'module'     => $pluginName,
                      'action'     => $methodName,
                      'parameters' => $finalParameters)
            );

            /**
             * This event is similar to the `API.Request.dispatch.end` event. It distinguishes the possibility to
             * subscribe only to the end of a specific API method instead of all API methods. You can use it for example
             * to modify the response. The passed parameters contains the returned value as well as some additional
             * meta information:
             *
             * ```
             * function (
             *     &$returnedValue,
             *     array('className'  => $className,
             *           'module'     => $pluginName,
             *           'action'     => $methodName,
             *           'parameters' => $finalParameters)
             * );
             * ```
             */
            Piwik::postEvent(sprintf('API.%s.%s.end', $pluginName, $methodName), $endHookParams);

            /**
             * Generic hook that plugins can use to modify any output of any API method. The event is triggered after
             * any API method is executed but before the result is send to the user. The parameters originally
             * passed to the controller are available as well:
             *
             * ```
             * function (
             *     &$returnedValue,
             *     array('className'  => $className,
             *           'module'     => $pluginName,
             *           'action'     => $methodName,
             *           'parameters' => $finalParameters)
             * );
             * ```
             */
            Piwik::postEvent(sprintf('API.Request.dispatch.end', $pluginName, $methodName), $endHookParams);

            // Restore the request
            $_GET = $saveGET;
            $_SERVER['QUERY_STRING'] = $saveQUERY_STRING;
        } catch (Exception $e) {
            $_GET = $saveGET;
            throw $e;
        }

        return $returnedValue;
    }

    /**
     * Returns the parameters names and default values for the method $name
     * of the class $class
     *
     * @param string $class The class name
     * @param string $name The method name
     * @return array  Format array(
     *                            'testParameter' => null, // no default value
     *                            'life'          => 42, // default value = 42
     *                            'date'          => 'yesterday',
     *                       );
     */
    public function getParametersList($class, $name)
    {
        return $this->metadataArray[$class][$name]['parameters'];
    }

    /**
     * Returns the 'moduleName' part of 'Piwik_moduleName_API' classname
     *
     * @param string $className "API"
     * @return string "Referrers"
     */
    public function getModuleNameFromClassName($className)
    {
        return str_replace(array('\\Piwik\\Plugins\\', '\\API'), '', $className);
    }

    /**
     * Sets whether to hide '@ignore'd functions from method metadata or not.
     *
     * @param bool $hideIgnoredFunctions
     */
    public function setHideIgnoredFunctions($hideIgnoredFunctions)
    {
        $this->hideIgnoredFunctions = $hideIgnoredFunctions;

        // make sure metadata gets reloaded
        $this->alreadyRegistered = array();
        $this->metadataArray = array();
    }

    /**
     * Returns an array containing the values of the parameters to pass to the method to call
     *
     * @param array $requiredParameters array of (parameter name, default value)
     * @param array $parametersRequest
     * @throws Exception
     * @return array values to pass to the function call
     */
    private function getRequestParametersArray($requiredParameters, $parametersRequest)
    {
        $finalParameters = array();
        foreach ($requiredParameters as $name => $defaultValue) {
            try {
                if ($defaultValue instanceof NoDefaultValue) {
                    $requestValue = Common::getRequestVar($name, null, null, $parametersRequest);
                } else {
                    try {

                        if ($name == 'segment' && !empty($parametersRequest['segment'])) {
                            // segment parameter is an exception: we do not want to sanitize user input or it would break the segment encoding
                            $requestValue = ($parametersRequest['segment']);
                        } else {
                            $requestValue = Common::getRequestVar($name, $defaultValue, null, $parametersRequest);
                        }
                    } catch (Exception $e) {
                        // Special case: empty parameter in the URL, should return the empty string
                        if (isset($parametersRequest[$name])
                            && $parametersRequest[$name] === ''
                        ) {
                            $requestValue = '';
                        } else {
                            $requestValue = $defaultValue;
                        }
                    }
                }
            } catch (Exception $e) {
                throw new Exception(Piwik::translateException('General_PleaseSpecifyValue', array($name)));
            }
            $finalParameters[] = $requestValue;
        }
        return $finalParameters;
    }

    /**
     * Includes the class API by looking up plugins/UserSettings/API.php
     *
     * @param string $fileName api class name eg. "API"
     * @throws Exception
     */
    private function includeApiFile($fileName)
    {
        $module = self::getModuleNameFromClassName($fileName);
        $path = PIWIK_INCLUDE_PATH . '/plugins/' . $module . '/API.php';

        if (is_readable($path)) {
            require_once $path; // prefixed by PIWIK_INCLUDE_PATH
        } else {
            throw new Exception("API module $module not found.");
        }
    }

    /**
     * @param string $class name of a class
     * @param ReflectionMethod $method instance of ReflectionMethod
     */
    private function loadMethodMetadata($class, $method)
    {
        if ($method->isPublic()
            && !$method->isConstructor()
            && $method->getName() != 'getInstance'
            && false === strstr($method->getDocComment(), '@deprecated')
            && (!$this->hideIgnoredFunctions || false === strstr($method->getDocComment(), '@ignore'))
        ) {
            $name = $method->getName();
            $parameters = $method->getParameters();

            $aParameters = array();
            foreach ($parameters as $parameter) {
                $nameVariable = $parameter->getName();

                $defaultValue = $this->noDefaultValue;
                if ($parameter->isDefaultValueAvailable()) {
                    $defaultValue = $parameter->getDefaultValue();
                }

                $aParameters[$nameVariable] = $defaultValue;
            }
            $this->metadataArray[$class][$name]['parameters'] = $aParameters;
            $this->metadataArray[$class][$name]['numberOfRequiredParameters'] = $method->getNumberOfRequiredParameters();
        }
    }

    /**
     * Checks that the method exists in the class
     *
     * @param string $className The class name
     * @param string $methodName The method name
     * @throws Exception If the method is not found
     */
    private function checkMethodExists($className, $methodName)
    {
        if (!$this->isMethodAvailable($className, $methodName)) {
            throw new Exception(Piwik::translateException('General_ExceptionMethodNotFound', array($methodName, $className)));
        }
    }

    /**
     * Returns the number of required parameters (parameters without default values).
     *
     * @param string $class The class name
     * @param string $name The method name
     * @return int The number of required parameters
     */
    private function getNumberOfRequiredParameters($class, $name)
    {
        return $this->metadataArray[$class][$name]['numberOfRequiredParameters'];
    }

    /**
     * Returns true if the method is found in the API of the given class name.
     *
     * @param string $className The class name
     * @param string $methodName The method name
     * @return bool
     */
    private function isMethodAvailable($className, $methodName)
    {
        return isset($this->metadataArray[$className][$methodName]);
    }

    /**
     * Checks that the class is a Singleton (presence of the getInstance() method)
     *
     * @param string $className The class name
     * @throws Exception If the class is not a Singleton
     */
    private function checkClassIsSingleton($className)
    {
        if (!method_exists($className, "getInstance")) {
            throw new Exception("$className that provide an API must be Singleton and have a 'static public function getInstance()' method.");
        }
    }
}

/**
 * To differentiate between "no value" and default value of null
 *
 * @package Piwik
 * @subpackage Piwik_API
 */
class NoDefaultValue
{
}
