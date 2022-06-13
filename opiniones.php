<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class Opiniones extends Module
{
    protected $config_form = false;

    const INSTALL_SQL_FILE = 'install.sql';

    private $_html = '';

    public function __construct()
    {
        $this->name = 'opiniones';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Alba';
        $this->need_instance = 0;

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('opiniones');
        $this->description = $this->l('modulo para dar reviews');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    public function install($keep = true)
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        if ($keep) {
            if (!file_exists(dirname(__FILE__) . '/' . self::INSTALL_SQL_FILE)) {
                return false;
            } elseif (!$sql = file_get_contents(dirname(__FILE__) . '/' . self::INSTALL_SQL_FILE)) {
                return false;
            }
            $sql = str_replace(['PREFIX_', 'ENGINE_TYPE'], [_DB_PREFIX_, _MYSQL_ENGINE_], $sql);
            $sql = preg_split("/;\s*[\r\n]+/", trim($sql));

            foreach ($sql as $query) {
                if (!Db::getInstance()->execute(trim($query))) {
                    return false;
                }
            }
        }

        if (
            parent::install() == false ||
            !$this->registerHook('displayFooterProduct') || //Product page footer
            !$this->registerHook('displayHeader') || //Adds css and javascript on front
            !$this->registerHook('displayProductListReviews') || //Product list miniature
            !$this->registerHook('displayProductAdditionalInfo') || //Display info in checkout column
            !$this->registerHook('filterProductContent') || // Add infos to Product page
            !$this->registerHook('registerGDPRConsent') ||
            !$this->registerHook('actionDeleteGDPRCustomer') ||
            !$this->registerHook('actionExportGDPRData') ||

            !Configuration::updateValue('PRODUCT_COMMENTS_MINIMAL_TIME', 30) ||
            !Configuration::updateValue('PRODUCT_COMMENTS_ALLOW_GUESTS', 0) ||
            !Configuration::updateValue('PRODUCT_COMMENTS_USEFULNESS', 1) ||
            !Configuration::updateValue('PRODUCT_COMMENTS_COMMENTS_PER_PAGE', 5) ||
            !Configuration::updateValue('PRODUCT_COMMENTS_ANONYMISATION', 0) ||
            !Configuration::updateValue('PRODUCT_COMMENTS_MODERATE', 1)
        ) {
            return false;
        }

        return true;
    }

    public function uninstall($keep = true)
    {
        if (
            !parent::uninstall() || ($keep && !$this->deleteTables()) ||
            !Configuration::deleteByName('PRODUCT_COMMENTS_MODERATE') ||
            !Configuration::deleteByName('PRODUCT_COMMENTS_COMMENTS_PER_PAGE') ||
            !Configuration::deleteByName('PRODUCT_COMMENTS_ANONYMISATION') ||
            !Configuration::deleteByName('PRODUCT_COMMENTS_ALLOW_GUESTS') ||
            !Configuration::deleteByName('PRODUCT_COMMENTS_USEFULNESS') ||
            !Configuration::deleteByName('PRODUCT_COMMENTS_MINIMAL_TIME') ||

            !$this->unregisterHook('registerGDPRConsent') ||
            !$this->unregisterHook('actionDeleteGDPRCustomer') ||
            !$this->unregisterHook('actionExportGDPRData') ||

            !$this->unregisterHook('displayProductAdditionalInfo') ||
            !$this->unregisterHook('displayHeader') ||
            !$this->unregisterHook('displayFooterProduct') ||
            !$this->unregisterHook('displayProductListReviews')
        ) {
            return false;
        }

        return true;
    }


    
    public function deleteTables()
    {
        return Db::getInstance()->execute('
			DROP TABLE IF EXISTS
			`' . _DB_PREFIX_ . 'product_comment`,
			`' . _DB_PREFIX_ . 'product_comment_grade`,
			`' . _DB_PREFIX_ . 'product_comment_usefulness`');
    }

    public function getCacheId($id_product = null)
    {
        return parent::getCacheId() . '|' . (int) $id_product;
    }

    
    public function getContent()
    {
        include_once dirname(__FILE__) . '/ProductComment.php';
        include_once dirname(__FILE__) . '/ProductCommentCriterion.php';

        $this->_html = '';
        $this->_postProcess();
        $this->_html .= $this->renderConfigForm();
        $this->_html .= $this->renderModerateLists();
        $this->_html .= $this->renderCommentsList();

        return $this->_html;
    }


    public function renderConfigForm()
    {
        $fields_form_1 = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Configuration', [], 'Modules.Productcomments.Admin'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'is_bool' => true, //retro compat 1.5
                        'label' => $this->trans('All reviews must be validated by an employee', [], 'Modules.Productcomments.Admin'),
                        'name' => 'PRODUCT_COMMENTS_MODERATE',
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Yes', [], 'Modules.Productcomments.Admin'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('No', [], 'Modules.Productcomments.Admin'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'is_bool' => true, //retro compat 1.5
                        'label' => $this->trans('Allow guest reviews', [], 'Modules.Productcomments.Admin'),
                        'name' => 'PRODUCT_COMMENTS_ALLOW_GUESTS',
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Yes', [], 'Modules.Productcomments.Admin'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('No', [], 'Modules.Productcomments.Admin'),
                            ],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Modules.Productcomments.Admin'),
                    'class' => 'btn btn-default pull-right',
                    'name' => 'submitModerate',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->name;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->module = $this;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitProducCommentsConfiguration';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false, [], ['configure' => $this->name, 'tab_module' => $this->tab, 'module_name' => $this->name]);
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$fields_form_1]);
    }

    public function renderModerateLists()
    {
        require_once dirname(__FILE__) . '/ProductComment.php';
        $return = null;

        if (Configuration::get('PRODUCT_COMMENTS_MODERATE')) {
            $comments = ProductComment::getByValidate(0, false);

            $fields_list = $this->getStandardFieldList();

            if (version_compare(_PS_VERSION_, '1.6', '<')) {
                $return .= '<h1>' . $this->trans('Reviews waiting for approval', [], 'Modules.Productcomments.Admin') . '</h1>';
                $actions = ['enable', 'delete'];
            } else {
                $actions = ['approve', 'delete'];
            }

            $helper = new HelperList();
            $helper->list_id = 'form-productcomments-moderate-list';
            $helper->shopLinkType = '';
            $helper->simple_header = true;
            $helper->actions = $actions;
            $helper->show_toolbar = false;
            $helper->module = $this;
            $helper->listTotal = count($comments);
            $helper->identifier = 'id_product_comment';
            $helper->title = $this->trans('Reviews waiting for approval', [], 'Modules.Productcomments.Admin');
            $helper->table = $this->name;
            $helper->table_id = 'waiting-approval-productcomments-list';
            $helper->token = Tools::getAdminTokenLite('AdminModules');
            $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
            $helper->no_link = true;

            $return .= $helper->generateList($comments, $fields_list);
        }


        return $return;
    }

    public function renderCommentsList()
    {
        require_once dirname(__FILE__) . '/ProductComment.php';

        $fields_list = $this->getStandardFieldList();

        $helper = new HelperList();
        $helper->list_id = 'form-productcomments-list';
        $helper->shopLinkType = '';
        $helper->simple_header = false;
        $helper->actions = ['delete'];
        $helper->show_toolbar = false;
        $helper->module = $this;
        $helper->identifier = 'id_product_comment';
        $helper->title = $this->trans('Approved Reviews', [], 'Modules.Productcomments.Admin');
        $helper->table = $this->name;
        $helper->table_id = 'approved-productcomments-list';
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->no_link = true;

        $page = ($page = Tools::getValue('submitFilter' . $helper->list_id)) ? $page : 1;
        $pagination = ($pagination = Tools::getValue($helper->list_id . '_pagination')) ? $pagination : 50;

        $moderate = Configuration::get('PRODUCT_COMMENTS_MODERATE');
        if (empty($moderate)) {
            $comments = ProductComment::getByValidate(0, false, (int) $page, (int) $pagination, true);
            $count = (int) ProductComment::getCountByValidate(0, true);
        } else {
            $comments = ProductComment::getByValidate(1, false, (int) $page, (int) $pagination);
            $count = (int) ProductComment::getCountByValidate(1);
        }

        $helper->listTotal = $count;

        return $helper->generateList($comments, $fields_list);
    }


    public function getConfigFieldsValues()
    {
        return [
            'PRODUCT_COMMENTS_MODERATE' => Tools::getValue('PRODUCT_COMMENTS_MODERATE', Configuration::get('PRODUCT_COMMENTS_MODERATE')),
            'PRODUCT_COMMENTS_ALLOW_GUESTS' => Tools::getValue('PRODUCT_COMMENTS_ALLOW_GUESTS', Configuration::get('PRODUCT_COMMENTS_ALLOW_GUESTS')),
        ];
    }
    protected function _postProcess()
    {
        if (Tools::isSubmit('submitModerate')) {
            Configuration::updateValue('PRODUCT_COMMENTS_MODERATE', (int) Tools::getValue('PRODUCT_COMMENTS_MODERATE'));
            Configuration::updateValue('PRODUCT_COMMENTS_ALLOW_GUESTS', (int) Tools::getValue('PRODUCT_COMMENTS_ALLOW_GUESTS'));
            $this->_html .= '<div class="conf confirm alert alert-success">' . $this->trans('Settings updated', [], 'Modules.Productcomments.Admin') . '</div>';

        $this->_clearcache('productcomments_reviews.tpl');
        }
    }

    public function displayApproveLink($token, $id, $name = null)
    {
        $this->smarty->assign([
            'href' => $this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->name, 'module_name' => $this->name, 'approveComment' => $id]),
            'action' => $this->trans('Approve', [], 'Modules.Productcomments.Admin'),
        ]);

        return $this->display(__FILE__, 'views/templates/admin/list_action_approve.tpl');
    }

    public function getStandardFieldList()
    {
        return [
            'id_product_comment' => [
                'title' => $this->trans('ID'),
                'type' => 'text',
                'search' => false,
                'class' => 'product-comment-id',
            ],
            'title' => [
                'title' => $this->trans('Review title'),
                'type' => 'text',
                'search' => false,
                'class' => 'product-comment-title',
            ],
            'content' => [
                'title' => $this->trans('Review'),
                'type' => 'text',
                'search' => false,
                'class' => 'product-comment-content',
            ],
            'grade' => [
                'title' => $this->trans('Rating'),
                'type' => 'text',
                'suffix' => '/5',
                'search' => false,
                'class' => 'product-comment-rating',
            ],
            'customer_name' => [
                'title' => $this->trans('Author'),
                'type' => 'text',
                'search' => false,
                'class' => 'product-comment-author',
                'callback' => 'renderAuthorName',
                'callback_object' => $this,
            ],
            'name' => [
                'title' => $this->trans('Product'),
                'type' => 'text',
                'search' => false,
                'class' => 'product-comment-product-name',
            ],
            'date_add' => [
                'title' => $this->trans('Time of publication'),
                'type' => 'date',
                'search' => false,
                'class' => 'product-comment-date',
            ],
        ];
    }

    public function renderAuthorName($value, $row)
    {
        if (!empty($row['customer_id'])) {
            $linkToCustomerProfile = $this->context->link->getAdminLink('AdminCustomers', false, [], [
                'id_customer' => $row['customer_id'],
                'viewcustomer' => 1,
            ]);

            return '<a href="' . $linkToCustomerProfile . '">' . $value . '</a>';
        }

        return $value;
    }


    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }
}
