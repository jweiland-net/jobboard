<?php

declare(strict_types=1);

/*
 * This file is part of the package jweiland/jobfair2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace JWeiland\Jobfair2\Backend\Element;

use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

/**
 * Custom renderType for TCA type=number/format=decimal fields.
 *
 * Renders a plain text input instead of the native <input type="number">.
 * Browsers filter out characters like "," while typing or pasting into a
 * real number input, which makes entering formatted amounts (e.g.
 * "3.421,84") impossible before TYPO3 even gets to see the value. A small
 * JavaScript module normalizes the typed value to the machine format
 * ("3421.84") on blur - by position, not by backend locale, since editors
 * enter values in whatever convention the source data uses, independent of
 * their own backend UI language. The field itself always shows the machine
 * format; locale-formatted display is a label concern (see label_alt /
 * label_userFunc on SalaryStep), not something this input should do.
 */
final class LocalizedDecimalElement extends AbstractFormElement
{
    protected $defaultFieldInformation = [
        'tcaDescription' => [
            'renderType' => 'tcaDescription',
        ],
    ];

    public function render(): array
    {
        $parameterArray = $this->data['parameterArray'];
        $resultArray = $this->initializeResultArray();

        $fieldInformationResult = $this->renderFieldInformation();
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldInformationResult, false);
        $fieldControlResult = $this->renderFieldControl();
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldControlResult, false);
        $fieldWizardResult = $this->renderFieldWizard();
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldWizardResult, false);

        $fieldId = StringUtility::getUniqueId('formengine-input-');

        $resultArray['javaScriptModules'][] = JavaScriptModuleInstruction::create(
            '@jweiland/jobfair2/form-engine-localized-decimal.js'
        )->invoke('initialize', $fieldId);

        $resultArray['html'] = $this->renderLabel($fieldId) . '
            <div class="formengine-field-item t3js-formengine-field-item">
                ' . $fieldInformationResult['html'] . $this->buildFieldHtml(
                    $fieldId,
                    (string)$parameterArray['itemFormElValue'],
                    $fieldControlResult['html'],
                    $fieldWizardResult['html']
                ) . '
            </div>';

        return $resultArray;
    }

    private function buildFieldHtml(string $fieldId, string $value, string $fieldControlHtml, string $fieldWizardHtml): string
    {
        $parameterArray = $this->data['parameterArray'];
        $config = $parameterArray['fieldConf']['config'];
        $width = $this->formMaxWidth(
            MathUtility::forceIntegerInRange($config['size'] ?? $this->defaultInputWidth, $this->minimumInputWidth, $this->maxInputWidth)
        );
        $attributes = [
            'value' => $value,
            'id' => $fieldId,
            'name' => (string)$parameterArray['itemFormElName'],
            'inputmode' => 'decimal',
            'class' => 'form-control form-control-clearable t3js-clearable',
            'data-formengine-validation-rules' => $this->getValidationDataAsJsonString($config),
        ];

        $html = [];
        $html[] = '<div class="form-control-wrap" style="max-width: ' . $width . 'px">';
        $html[] =     '<div class="form-wizards-wrap">';
        $html[] =         '<div class="form-wizards-item-element">';
        $html[] =             '<input type="text" ' . GeneralUtility::implodeAttributes($attributes, true) . ' />';
        $html[] =         '</div>';
        if ($fieldControlHtml !== '') {
            $html[] =         '<div class="form-wizards-item-aside form-wizards-item-aside--field-control">';
            $html[] =             '<div class="btn-group">' . $fieldControlHtml . '</div>';
            $html[] =         '</div>';
        }
        if ($fieldWizardHtml !== '') {
            $html[] =         '<div class="form-wizards-item-bottom">' . $fieldWizardHtml . '</div>';
        }
        $html[] =     '</div>';
        $html[] = '</div>';

        return implode(LF, $html);
    }
}
