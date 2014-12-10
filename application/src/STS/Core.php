<?php
namespace STS;

use STS\Core\Api\DefaultAuthFacade;
use STS\Core\Api\DefaultProfessionalGroupFacade;
use STS\Core\Api\DefaultSchoolFacade;
use STS\Core\Api\DefaultMemberFacade;
use STS\Core\Api\DefaultSurveyFacade;
use STS\Core\Api\DefaultPresentationFacade;
use STS\Core\Api\DefaultLocationFacade;
use STS\Core\Api\DefaultUserFacade;
use STS\Core\Api\DefaultMailerFacade;

class Core
{
    const CORE_CONFIG_PATH = '/config/core.xml';

    private $config;

    static protected $default;

    public function __construct(\Zend_Config $config)
    {
        $this->config = $config;
    }
    public function load($key)
    {
        switch ($key) {
            case 'AuthFacade':
                $facade = DefaultAuthFacade::getDefaultInstance($this->config);
                break;
            case 'SchoolFacade':
                $facade = DefaultSchoolFacade::getDefaultInstance($this->config);
                break;
            case 'MemberFacade':
                $facade = DefaultMemberFacade::getDefaultInstance($this->config);
                break;
            case 'SurveyFacade':
                $facade = DefaultSurveyFacade::getDefaultInstance($this->config);
                break;
            case 'PresentationFacade':
                $facade = DefaultPresentationFacade::getDefaultInstance($this->config);
                break;
            case 'LocationFacade':
                $facade = DefaultLocationFacade::getDefaultInstance($this->config);
                break;
            case 'UserFacade':
                $facade = DefaultUserFacade::getDefaultInstance($this->config);
                break;
            case 'MailerFacade':
                $facade = DefaultMailerFacade::getDefaultInstance($this->config);
                break;
            case 'ProfessionalGroupFacade':
                $facade = DefaultProfessionalGroupFacade::getDefaultInstance($this->config);
                break;
            default:
                throw new \InvalidArgumentException("Class does not exist ($key)");
        }
        return $facade;
    }

    /**
     * @return \Zend_Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * getDefaultInstance
     *
     * @return Core
     */
    public static function getDefaultInstance()
    {
        if (!isset(self::$default)) {
            $configPath = APPLICATION_PATH . self::CORE_CONFIG_PATH;
            $config = new \Zend_Config_Xml($configPath, 'all');
            self::$default = new Core($config);
        }

        return self::$default;
    }
}
