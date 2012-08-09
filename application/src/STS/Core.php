<?php
namespace STS;
use STS\Core\Api\DefaultAuthFacade;
use STS\Core\Api\DefaultSchoolFacade;
use STS\Core\Api\DefaultMemberFacade;

class Core
{
    const CORE_CONFIG_PATH = '/config/core.xml';

    private $config;
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
            default:
                throw new \InvalidArgumentException("Class does not exist ($key)");
                break;
        }
        return $facade;
    }
    public static function getDefaultInstance()
    {
        $configPath = APPLICATION_PATH . self::CORE_CONFIG_PATH;
        $config = new \Zend_Config_Xml($configPath, 'all');
        $instance = new Core($config);
        return $instance;
    }
}
