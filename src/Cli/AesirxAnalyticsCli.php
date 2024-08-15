<?php

/**
 * @package     AesirX_Analytics_Library
 *
 * @copyright   Copyright (C) 2016 - 2023 Aesir. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace AesirxAnalyticsLib\Cli;

use Aesirx\Component\AesirxAnalytics\Administrator\Cli\wpdb;
use AesirxAnalyticsLib\Exception\ExceptionWithErrorType;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Database;
use RuntimeException;
use AesirxAnalytics\AesirxAnalyticsMysqlHelper;

$folderPath = WP_PLUGIN_DIR . '/aesirx-analytics/src/Mysql';

$files = glob($folderPath . '/*.php');

foreach ($files as $file) {
    include_once $file;
}

/**
 * @since 1.0.0
 */
class AesirxAnalyticsCli
{
    private $cliPath;

    private $env;

    public function __construct(Env $env, string $cliPath)
    {
        $this->cliPath = $cliPath;
        $this->env = $env;
    }

    public function analyticsCliExists(): bool
    {
        return file_exists($this->cliPath);
    }

    /**
     * @param array $command
     * @param bool  $makeExecutable
     *
     * @return Process
     * @throws ExceptionWithErrorType
     *
     */
    public function processAnalytics(array $command, bool $makeExecutable = true)
    {
       $method = $_SERVER['REQUEST_METHOD'];

        if ($method == "GET") {
            if ($command[0] == 'statistics') {

                switch ($command[1]) {
                    case 'attributes':
                        $class = new \AesirX_Analytics_Get_Attribute_Value();
                        break;
    
                    case 'attribute-date':
                        $class = new \AesirX_Analytics_Get_Attribute_Value_Date();
                        break;
    
                    case 'channels':
                        $class = new \AesirX_Analytics_Get_All_Channels();
                        break;
    
                    case 'cities':
                        $class = new \AesirX_Analytics_Get_All_Cities();
                        break;
    
                    case 'countries':
                        $class = new \AesirX_Analytics_Get_All_Countries();
                        break;
    
                    case 'regions':
                        $class = new \AesirX_Analytics_Get_All_Regions();
                        break;
    
                    case 'browserversions':
                        $class = new \AesirX_Analytics_Get_All_Browser_Versions();
                        break;
    
                    case 'browsers':
                        $class = new \AesirX_Analytics_Get_All_Browsers();
                        break;
    
                    case 'metrics':
                        $class = new \AesirX_Analytics_Get_Metrics_All();
                        break;
    
                    case 'visitors':
                        $class = new \AesirX_Analytics_Get_All_Visitors();
                        break;
    
                    case 'devices':
                        $class = new \AesirX_Analytics_Get_All_Devices();
                        break;
    
                    case 'pages':
                        $class = new \AesirX_Analytics_Get_All_Pages();
                        break;
                        
                    case 'referrers':
                        $class = new \AesirX_Analytics_Get_All_Referrers();
                        break;
    
                    case 'events-name-type':
                        $class = new \AesirX_Analytics_Get_All_Event_Name_Type();
                        break;
    
                    case 'attribute':
                        $class = new \AesirX_Analytics_Get_All_Attribute();
                        break;
                    
                    case 'visits':
                        $class = new \AesirX_Analytics_Get_All_Events();
                        break;
                    
                    case 'outlinks':
                        $class = new \AesirX_Analytics_Get_All_Outlinks();
                        break;
    
                    case 'events':
                        $class = new \AesirX_Analytics_Get_List_Events();
                        break;
                    
                    case 'languages':
                        $class = new \AesirX_Analytics_Get_All_Languages();
                        break;
                    
                    case 'isps':
                        $class = new \AesirX_Analytics_Get_All_Languages();
                        break;
                    
                    default:
                        $class = new \AesirX_Analytics_Not_Found();
                        break;
                }
            }
    
            if ($command[0] == 'get') {
    
                switch ($command[1]) {
                    case 'events':
                        $class = new \AesirX_Analytics_Get_All_Events_Name();
                        break;

                    case 'flow':
                        $class = new \AesirX_Analytics_Get_All_Flows();
                        break;
                    
                    case 'flows':
                        $class = new \AesirX_Analytics_Get_All_Flows();
                        break;
    
                    case 'flows-date':
                        $class = new \AesirX_Analytics_Get_All_Flows_Date();
                        break;

                    case 'visitor':
                        $class = new \AesirX_Analytics_Get_Visitor_Consent_List();
                        break;
                    
                    default:
                        $class = new \AesirX_Analytics_Not_Found();
                        break;
                }
            }
    
            if ($command[0] == 'list-consent-statistics') {
    
                switch ($command[1]) {
                    case 'all':
                        $class = new \AesirX_Analytics_Get_All_Consents();
                        break;
    
                    case 'total-consents-by-date':
                        $class = new \AesirX_Analytics_Get_Total_Consent_Per_Day();
                        break;
                    
                    case 'total-tiers-by-date':
                        $class = new \AesirX_Analytics_Get_Total_Consent_Tier();
                        break;
                    
                    default:
                        $class = new \AesirX_Analytics_Not_Found();
                        break;
                }
            }
    
            if ($command[0] == 'conversion') {
    
                switch ($command[1]) {
                    case 'products':
                        $class = new \AesirX_Analytics_Get_Conversion_Product();
                        break;
    
                    case 'products-chart':
                        $class = new \AesirX_Analytics_Get_Conversion_Product_Chart();
                        break;
                    
                    case 'statistics':
                        $class = new \AesirX_Analytics_Get_Conversion_Statistic();
                        break;
                    
                    case 'statistics-chart':
                        $class = new \AesirX_Analytics_Get_Conversion_Statistic_Chart();
                        break;
                    
                    default:
                        $class = new \AesirX_Analytics_Not_Found();
                        break;
                }
            }

            if ($command[0] == 'live-visitors') {
    
                switch ($command[1]) {
                    case 'list':
                        $class = new \AesirX_Analytics_Get_Live_Visitors_List();
                        break;
    
                    case 'total':
                        $class = new \AesirX_Analytics_Get_Live_Visitors_Total();
                        break;
                    
                    case 'device':
                        $class = new \AesirX_Analytics_Get_Live_Visitors_Device();
                        break;
                    
                    default:
                        $class = new \AesirX_Analytics_Not_Found();
                        break;
                }
            }

            if ($command[0] == 'datastream') {
                $class = new \AesirX_Analytics_Get_Datastream_Template();
            }
        }
        else if ($method == "POST") {
            if ($command[0] == 'visitor') {
                
                if ($command[1] == 'start') {

                    switch ($command[2]) {
                        case 'v2':
                            $class = new \AesirX_Analytics_Start_Fingerprint();
                            break;
        
                        default:
                            $class = new \AesirX_Analytics_Not_Found();
                            break;
                    }
                }
                elseif ($command[1] == 'end') {

                    switch ($command[2]) {
                        case 'v1':
                            $class = new \AesirX_Analytics_Close_Visitor_Event();
                            break;
        
                        default:
                            $class = new \AesirX_Analytics_Not_Found();
                            break;
                    }
                }
            }

            if ($command[0] == 'wallet') {
                $class = new \AesirX_Analytics_Get_Nonce();
            }

            if ($command[0] == 'job') {
                switch ($command[1]) {
                    case 'geo':
                        $class = new \AesirX_Analytics_Job_Geo();
                        break;
                        
                    default:
                        $class = new \AesirX_Analytics_Not_Found();
                        break;
                }  
            }

            if ($command[0] == 'conversion') {
                switch ($command[1]) {
                    case 'replace':
                        $class = new \AesirX_Analytics_Conversion_Replace();
                        break;
                        
                    default:
                        $class = new \AesirX_Analytics_Not_Found();
                        break;
                }  
            }

            if ($command[0] == 'consent') {
                switch ($command[1]) {
                    case 'level1':
                        $class = new \AesirX_Analytics_Add_Consent_Level1();
                        break;

                    case 'level2':
                        $class = new \AesirX_Analytics_Add_Consent_Level2();
                        break;

                    case 'level3':
                    case 'level4':
                        $class = new \AesirX_Analytics_Add_Consent_Level3or4();
                        break;
    
                    default:
                        $class = new \AesirX_Analytics_Not_Found();
                        break;
                }
            }

            if ($command[0] == 'datastream') {
                $class = new \AesirX_Analytics_Store_Datastream_Template();
            }

        } else if ($method == "PUT") {
            if ($command[0] == 'revoke') {
                switch ($command[1]) {
                    case 'level1':
                        $class = new \AesirX_Analytics_Revoke_Consent_Level1();
                        break;

                    case 'level2':
                        $class = new \AesirX_Analytics_Revoke_Consent_Level2();
                        break;

                    case 'level3':
                    case 'level4':
                        $class = new \AesirX_Analytics_Revoke_Consent_Level3or4();
                        break;
    
                    default:
                        $class = new \AesirX_Analytics_Not_Found();
                        break;
                }
            }
        }

        $data = $class->aesirx_analytics_mysql_execute($command);  

        return json_encode($data);
    }
}
