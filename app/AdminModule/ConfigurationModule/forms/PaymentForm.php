<?php

namespace App\AdminModule\ConfigurationModule\Forms;

use App\AdminModule\Forms\BaseForm;
use App\Model\Enums\MaturityType;
use App\Model\Enums\VariableSymbolType;
use App\Model\Settings\Settings;
use App\Model\Settings\SettingsRepository;
use App\Model\User\UserRepository;
use Nette;
use Nette\Application\UI\Form;


/**
 * Formulář pro nastavení platby.
 *
 * @author Michal Májský
 * @author Jan Staněk <jan.stanek@skaut.cz>
 */
class PaymentForm extends Nette\Object
{
    /** @var BaseForm */
    private $baseForm;

    /** @var SettingsRepository */
    private $settingsRepository;

    /** @var UserRepository */
    private $userRepository;


    /**
     * PaymentForm constructor.
     * @param BaseForm $baseForm
     * @param SettingsRepository $settingsRepository
     * @param UserRepository $userRepository
     */
    public function __construct(BaseForm $baseForm, SettingsRepository $settingsRepository, UserRepository $userRepository)
    {
        $this->baseForm = $baseForm;
        $this->settingsRepository = $settingsRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * Vytvoří formulář.
     * @return Form
     */
    public function create()
    {
        $form = $this->baseForm->create();

        $renderer = $form->getRenderer();
        $renderer->wrappers['control']['container'] = 'div class="col-sm-7 col-xs-7"';
        $renderer->wrappers['label']['container'] = 'div class="col-sm-5 col-xs-5 control-label"';

        $form->addText('accountNumber', 'admin.configuration.account_number')
            ->addRule(Form::FILLED, 'admin.configuration.account_number_empty')
            ->addRule(Form::PATTERN, 'admin.configuration.account_number_format', '^(\d{1,6}-|)\d{2,10}\/\d{4}$');

        $form->addText('variableSymbolCode', 'admin.configuration.variable_symbol_code')
            ->setRequired(FALSE)
            ->addRule(Form::PATTERN, 'admin.configuration.variable_symbol_code_format', '^\d{0,4}$');

        $form->addSelect('variableSymbolType', 'admin.configuration.variable_symbol_type', $this->prepareVariableSympolTypeOptions());

        $maturityTypeSelect = $form->addSelect('maturityType', 'admin.configuration.maturity_type', $this->prepareMaturityTypeOptions());
        $maturityTypeSelect->addCondition($form::EQUAL, MaturityType::DATE)->toggle('maturity-date');
        $maturityTypeSelect->addCondition($form::EQUAL, MaturityType::DAYS)->toggle('maturity-days');
        $maturityTypeSelect->addCondition($form::EQUAL, MaturityType::WORK_DAYS)->toggle('maturity-work-days');

        $form->addDatePicker('maturityDate', 'admin.configuration.maturity_date')
            ->setOption('id', 'maturity-date');

        $form->addText('maturityDays', 'admin.configuration.maturity_days')
            ->setOption('id', 'maturity-days')
            ->addCondition(Form::FILLED)
            ->addRule(Form::INTEGER, 'admin.configuration.maturity_days_format');

        $form->addText('maturityWorkDays', 'admin.configuration.maturity_work_days')
            ->setOption('id', 'maturity-work-days')
            ->addCondition(Form::FILLED)
            ->addRule(Form::INTEGER, 'admin.configuration.maturity_work_days_format');


        $form->addSubmit('submit', 'admin.common.save');

        $form->setDefaults([
            'accountNumber' => $this->settingsRepository->getValue(Settings::ACCOUNT_NUMBER),
            'variableSymbolCode' => $this->settingsRepository->getValue(Settings::VARIABLE_SYMBOL_CODE),
            'variableSymbolType' => $this->settingsRepository->getValue(Settings::VARIABLE_SYMBOL_TYPE),
            'maturityType' => $this->settingsRepository->getValue(Settings::MATURITY_TYPE),
            'maturityDate' => $this->settingsRepository->getValue(Settings::MATURITY_DATE),
            'maturityDays' => $this->settingsRepository->getValue(Settings::MATURITY_DAYS),
            'maturityWorkDays' => $this->settingsRepository->getValue(Settings::MATURITY_WORK_DAYS)
        ]);

        $form->onSuccess[] = [$this, 'processForm'];

        return $form;
    }

    /**
     * Zpracuje formulář.
     * @param Form $form
     * @param \stdClass $values
     */
    public function processForm(Form $form, \stdClass $values)
    {
        $this->settingsRepository->setValue(Settings::ACCOUNT_NUMBER, $values['accountNumber']);
        $this->settingsRepository->setValue(Settings::VARIABLE_SYMBOL_CODE,  $values['variableSymbolCode']);
        $this->settingsRepository->setValue(Settings::VARIABLE_SYMBOL_TYPE, $values['variableSymbolType']);
        $this->settingsRepository->setValue(Settings::MATURITY_TYPE, $values['maturityType']);

        if (array_key_exists('maturityDate', $values))
            $this->settingsRepository->setValue(Settings::MATURITY_DATE, $values['maturityDate']);

        if (array_key_exists('maturityDays', $values))
            $this->settingsRepository->setValue(Settings::MATURITY_DAYS, $values['maturityDays']);

        if (array_key_exists('maturityWorkDays', $values))
            $this->settingsRepository->setValue(Settings::MATURITY_WORK_DAYS, $values['maturityWorkDays']);
    }

    /**
     * Vrátí typy generování variabilního symbolu jako možnosti pro select.
     * @return array
     */
    private function prepareVariableSympolTypeOptions()
    {
        $options = [];
        foreach (VariableSymbolType::$types as $type)
            $options[$type] = 'common.variable_symbol_type.' . $type;
        return $options;
    }

    /**
     * Vrátí způsoby výpočtu splatnosti jako možnosti pro select.
     * @return array
     */
    private function prepareMaturityTypeOptions()
    {
        $options = [];
        foreach (MaturityType::$types as $type)
            $options[$type] = 'common.maturity_type.' . $type;
        return $options;
    }
}
