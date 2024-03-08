<?php

$GLOBALS['TL_DCA']['tl_settings']['palettes']['default'] .= ';{legend_comatrack_global},comatrack_exclude_ip,comatrack_exclude_ua';

$GLOBALS['TL_DCA']['tl_settings']['fields']['comatrack_exclude_ip'] = [
            'label' => &$GLOBALS['TL_LANG']['tl_settings']['comatrack_exclude_ip'],
            'default' => '',
            'inputType' => 'textarea',
            'eval' => ['mandatory' => false, 'rows' => 3, 'allowHtml' => false],
            'load_callback' => [['tl_settings_comatrack', 'comatrackSettingsLoadCallback']],
            'save_callback' => [['tl_settings_comatrack', 'comatrackSettingsSaveCallback']],
        ];

$GLOBALS['TL_DCA']['tl_settings']['fields']['comatrack_exclude_ua'] = [
            'label' => &$GLOBALS['TL_LANG']['tl_settings']['comatrack_exclude_ua'],
            'default' => '',
            'inputType' => 'textarea',
            'eval' => ['mandatory' => false, 'rows' => 3, 'allowHtml' => false],
            'load_callback' => [['tl_settings_comatrack', 'comatrackSettingsLoadCallback']],
            'save_callback' => [['tl_settings_comatrack', 'comatrackSettingsSaveCallback']],
        ];

class tl_settings_comatrack extends Contao\Backend
{
    public function comatrackSettingsSaveCallback($varValue, $dc)
    {
        /** @var $dc DataContainer|DC_File */
        $retValues = [];

        $varValue = explode("\n", $varValue);
        if (count($varValue) > 0) {
            foreach ($varValue as $val) {
                if (trim($val)) {
                    $retValues[] = trim($val);
                }
            }
        }

        return implode('~~~', $retValues);
    }

    public function comatrackSettingsLoadCallback($varValue, $dc)
    {
        /* @var $dc DataContainer|DC_File */

        return str_replace('~~~', "\n", $varValue);
    }
}
