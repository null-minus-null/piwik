<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik
 * @package SmartyPlugins
 */
namespace Piwik\View;

use Piwik\FrontController;
use Piwik\Url;
use Piwik\View;

/**
 * A facade that makes it easier to use the '_reportsByDimension.twig' template.
 *
 * This view will output HTML that displays a list of report names by category and
 * loads them by AJAX when clicked. The loaded report is displayed to the right
 * of the report listing.
 */
class ReportsByDimension extends View
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct('@CoreHome/ReportsByDimension/_reportsByDimension');
        $this->dimensionCategories = array();
    }

    /**
     * Adds a report to the list of reports to display.
     *
     * @param string $category The report's category. Can be a i18n token.
     * @param string $title The report's title. Can be a i18n token.
     * @param string $action The controller action used to load the report, ie, Referrers.getAll
     * @param array $params The list of query parameters to use when loading the report.
     *                      This list overrides query parameters currently in use. For example,
     *                        array('idSite' => 2, 'viewDataTable' => 'goalsTable')
     *                      would mean the goals report for site w/ ID=2 will always be loaded.
     */
    public function addReport($category, $title, $action, $params = array())
    {
        list($module, $action) = explode('.', $action);
        $params = array('module' => $module, 'action' => $action) + $params;

        $categories = $this->dimensionCategories;
        $categories[$category][] = array(
            'title'  => $title,
            'params' => $params,
            'url'    => Url::getCurrentQueryStringWithParametersModified($params)
        );
        $this->dimensionCategories = $categories;
    }

    /**
     * Adds a set of reports to the list of reports to display.
     *
     * @param array $reports An array containing report information. The array requires
     *                       the 'category', 'title', 'action' and 'params' elements.
     *                       For information on what they should contain, @see addReport.
     */
    public function addReports($reports)
    {
        foreach ($reports as $report) {
            $this->addReport($report['category'], $report['title'], $report['action'], $report['params']);
        }
    }

    /**
     * Renders this view.
     *
     * @return string The rendered view.
     */
    public function render()
    {
        $this->firstReport = "";

        // if there are reports & report categories added, render the first one so we can
        // display it initially
        $categories = $this->dimensionCategories;
        if (!empty($categories)) {
            $firstCategory = reset($categories);
            $firstReportInfo = reset($firstCategory);

            $oldGet = $_GET;
            $oldPost = $_POST;

            foreach ($firstReportInfo['params'] as $key => $value) {
                $_GET[$key] = $value;
            }

            $_POST = array();

            $module = $firstReportInfo['params']['module'];
            $action = $firstReportInfo['params']['action'];
            $this->firstReport = FrontController::getInstance()->fetchDispatch($module, $action);

            $_GET = $oldGet;
            $_POST = $oldPost;
        }

        return parent::render();
    }
}
