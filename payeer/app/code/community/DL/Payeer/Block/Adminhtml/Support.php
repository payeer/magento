<?php

class DL_Payeer_Block_Adminhtml_Support
    extends Mage_Adminhtml_Block_Abstract
    implements Varien_Data_Form_Element_Renderer_Interface
{
 
    public function render(Varien_Data_Form_Element_Abstract $element)
    {
        $helper = Mage::helper('dlpayeer');
        $moduleNameId = 'DL_Payeer';
        $html =
            '';
        return $html;
    }

    protected function _getConfigValue($module, $config)
    {
        $locale = Mage::app()->getLocale()->getLocaleCode();
        $defaultLocale = 'en_US';
        $mainConfig = Mage::getConfig();
        $moduleConfig = $mainConfig->getNode('modules/' . $module . '/' . $config);

        if ((string)$moduleConfig) {
            return $moduleConfig;
        }

        if ($moduleConfig->$locale) {
            return $moduleConfig->$locale;
        } else {
            return $moduleConfig->$defaultLocale;
        }
    }

    const PLATFORM_CE = 'ce';
    const PLATFORM_PE = 'pe';
    const PLATFORM_EE = 'ee';
    const PLATFORM_GO = 'go';
    const PLATFORM_UNKNOWN = 'unknown';

    protected static $_platformCode = self::PLATFORM_UNKNOWN;

    protected function _getPlatform()
    {
        if (self::$_platformCode == self::PLATFORM_UNKNOWN) 
		{
            if (property_exists('Mage', '_currentEdition')) {
                switch (Mage::getEdition()) {
                    case Mage::EDITION_COMMUNITY:
                        self::$_platformCode = self::PLATFORM_CE;
                        break;
                    case Mage::EDITION_PROFESSIONAL:
                        self::$_platformCode = self::PLATFORM_PE;
                        break;
                    case Mage::EDITION_ENTERPRISE:
                        self::$_platformCode = self::PLATFORM_EE;
                        break;
                    case Mage::EDITION_ENTERPRISE:
                        self::$_platformCode = self::PLATFORM_EE;
                        break;
                    default:
                        self::$_platformCode = self::PLATFORM_UNKNOWN;
                }
            }

            if (self::$_platformCode == self::PLATFORM_UNKNOWN) {
                $modulesArray = (array)Mage::getConfig()->getNode('modules')->children();
                $isEnterprise = array_key_exists('Enterprise_Enterprise', $modulesArray);

                $isProfessional = false; 
                $isGo = false; 

                if ($isEnterprise) {
                    self::$_platformCode = self::PLATFORM_EE;
                } elseif ($isProfessional) {
                    self::$_platformCode = self::PLATFORM_PE;
                } elseif ($isGo) {
                    self::$_platformCode = self::PLATFORM_GO;
                } else {
                    self::$_platformCode = self::PLATFORM_CE;
                }
            }
        }
		
        return self::$_platformCode;
    }

}