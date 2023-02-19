<?php
/**
 * Project : everpsmailcopy
 * @author Team Ever
 * @copyright Team Ever
 * @license   Tous droits réservés / Le droit d'auteur s'applique (All rights reserved / French copyright law applies)
 * @link https://www.team-ever.com
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Everpsmailcopy extends Module
{
    private $html;
    private $postErrors = [];

    public function __construct()
    {
        $this->name = 'everpsmailcopy';
        $this->displayName = $this->l('Mail copy');
        $this->description = $this->l('Receive a copy of mails');
        $this->tab = 'administration';
        $this->version = '1.0.3';
        $this->author = 'Team Ever';
        $this->need_instance = 1;
        $this->bootstrap = true;
        parent::__construct();
    }

    public function install()
    {
        return parent::install();
    }

    public function checkHooks()
    {
        return $this->registerHook('actionEmailSendBefore');
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        $this->checkHooks();
        if (Tools::isSubmit('submitConf')) {
            $this->postValidation();

            if (!count($this->postErrors)) {
                $this->postProcess();
            }
        }
        if (count($this->postErrors)) {
            foreach ($this->postErrors as $error) {
                $this->html .= $this->displayError($error);
            }
        }
        $this->context->smarty->assign([
            'everpsmailcopy_dir' => $this->_path,
        ]);
        $this->html .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/header.tpl');
        $this->html .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');
        $this->html .= $this->renderForm();
        $this->html .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/footer.tpl');

        return $this->html;
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitConf';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];
        return $helper->generateForm($this->getConfigForm());
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        $employees = $this->getEmployees();
        $form_fields = [];
        $form_fields[] = [
            'form' => [
                'legend' => [
                    'title' => $this->l('General Settings'),
                    'icon' => 'icon-smile',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Enable copies only for mails sent to customers ?'),
                        'desc' => $this->l('Else you will receive a copy for each sent email'),
                        'hint' => $this->l('Do you want to receive only a copy of emails sent to your customers ?'),
                        'name' => 'EVER_COPY_MAIL_ONLY_CUST',
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('No'),
                            ]
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Send copy to those employees'),
                        'hint' => $this->l('Will send mail copy to those employees'),
                        'desc' => $this->l('Please select at least one employee'),
                        'name' => 'EVER_COPY_MAIL_EMPLOYEES[]',
                        'class' => 'chosen',
                        'multiple' => true,
                        'required' => true,
                        'options' => [
                            'query' => $employees,
                            'id' => 'id_employee',
                            'name' => 'email'
                        ]
                    ]
                ],
                'submit' => [
                    'name' => 'submit',
                    'title' => $this->l('Save'),
                ],
            ],
        ];
        return $form_fields;
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return [
            'EVER_COPY_MAIL_ONLY_CUST' => Configuration::get('EVER_COPY_MAIL_ONLY_CUST'),
            'EVER_COPY_MAIL_EMPLOYEES[]' => Tools::getValue(
                'EVER_COPY_MAIL_EMPLOYEES',
                json_decode(
                    Configuration::get(
                        'EVER_COPY_MAIL_EMPLOYEES'
                    )
                )
            ),
        ];
    }

    protected function postValidation()
    {
        if (Tools::getValue('EVER_COPY_MAIL_ONLY_CUST')
            && !Validate::isBool(Tools::getValue('EVER_COPY_MAIL_ONLY_CUST'))
        ) {
            $this->postErrors[] = $this->l('Error : [Send copies only on customer emails] is not valid');
        }
        if (!Tools::getValue('EVER_COPY_MAIL_EMPLOYEES')
            || !Validate::isArrayWithIds(Tools::getValue('EVER_COPY_MAIL_EMPLOYEES'))
        ) {
            $this->postErrors[] = $this->l('Error : [Selected employees] is not valid');
        }
    }

    /**
     * Save form data.
     */
    public function postProcess()
    {
        if (Tools::isSubmit('submitConf')) {
            $form_values = $this->getConfigFormValues();
            foreach (array_keys($form_values) as $key) {
                if ($key == 'EVER_COPY_MAIL_EMPLOYEES[]') {
                    Configuration::updateValue(
                        'EVER_COPY_MAIL_EMPLOYEES',
                        json_encode(Tools::getValue('EVER_COPY_MAIL_EMPLOYEES')),
                        true
                    );
                } else {
                    Configuration::updateValue($key, Tools::getValue($key));
                }
            }
        }

        $this->html .= $this->displayConfirmation(
            $this->l('Setting updated')
        );
    }

    protected function getCopyToEmployees()
    {
        $returnedEmployees = [];
        $employeesIds = Configuration::get('EVER_COPY_MAIL_EMPLOYEES');
        if ($employeesIds) {
            $decoded = json_decode($employeesIds);
            foreach ($decoded as $idEmployee) {
                $employee = new Employee(
                    (int)$idEmployee
                );
                $returnedEmployees[] = $employee;
            }
        }
        return $returnedEmployees;
    }

    protected function getEmployees($activeOnly = true)
    {
        return Db::getInstance()->executeS('
            SELECT `id_employee`, `firstname`, `lastname`, `email`
            FROM `' . _DB_PREFIX_ . 'employee`
            ' . ($activeOnly ? ' WHERE `active` = 1' : '') . '
            ORDER BY `lastname` ASC
        ');
    }

    protected function isCustomer($email)
    {
        $customer = new Customer();
        $obj = $customer->getByEmail($email);
        return (bool)Validate::isLoadedObject($obj);
    }

    protected function isEmployee($email)
    {
        $employee = new Employee();
        $obj = $employee->getByEmail($email);
        return (bool)Validate::isLoadedObject($obj);
    }

    /**
     * Send BCC to selected employees
     * @return params
     */
    public function hookActionEmailSendBefore($params)
    {
        $copyTo = $this->getCopyToEmployees();
        $isSentToCustomer = $this->isCustomer($params['to']);
        $isSentToEmployee = $this->isEmployee($params['to']);
        // This is a parameter as some mails are sent even if customer isn't stored on current shop
        // So this option allows to receive all emails or just emails sent to customers
        // Useful: this can lock emails sent to employees too
        if ((bool)Configuration::get('EVER_COPY_MAIL_ONLY_CUST') === true
            && (bool)$isSentToCustomer === false
        ) {
            return;
        }
        // Lock employees emails
        if ((bool)$isSentToEmployee === false) {
            return;
        }
        $bcc = [];
        if (isset($params['bcc']) && is_array($params['bcc'])) {
            foreach ($params['bcc'] as $addr) {
                $addr = trim($addr);
                if (Validate::isEmail($addr)) {
                    $bcc[] = $addr;
                }
            }
        } elseif (isset($params['bcc'])) {
            $bcc[] = $bcc;
        }

        foreach ($copyTo as $employee) {
            $bcc[] = $employee->email;
            
        }
        if (count($bcc) > 0) {
            $params['bcc'] = $bcc;
        }
        return $params;
    }
}
