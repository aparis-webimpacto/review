<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class ProductComment extends ObjectModel
{
    public $id;

    public $id_product;

    public $id_customer;

    public $id_guest;

    public $customer_name;

    public $title;

    public $content;

    public $grade;

    public $validate = false;

    public $deleted = false;

    public $date_add;

    public static $definition = [
        'table' => 'product_comment',
        'primary' => 'id_product_comment',
        'fields' => [
            'id_product' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_customer' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
            'id_guest' => ['type' => self::TYPE_INT],
            'customer_name' => ['type' => self::TYPE_STRING],
            'title' => ['type' => self::TYPE_STRING],
            'content' => ['type' => self::TYPE_STRING, 'validate' => 'isMessage', 'size' => 65535, 'required' => true],
            'grade' => ['type' => self::TYPE_FLOAT, 'validate' => 'isFloat'],
            'validate' => ['type' => self::TYPE_BOOL, 'validate' => 'isBool'],
            'deleted' => ['type' => self::TYPE_BOOL],
            'date_add' => ['type' => self::TYPE_DATE],
        ],
    ];



    public static function getByValidate($validate = '0', $deleted = false, $p = null, $limit = null, $skip_validate = false)
    {
        $sql = '
			SELECT pc.`id_product_comment`, pc.`id_product`, c.id_customer AS customer_id, IF(c.id_customer, CONCAT(c.`firstname`, \' \',  c.`lastname`), pc.customer_name) customer_name, pc.`title`, pc.`content`, pc.`grade`, pc.`date_add`
			FROM `' . _DB_PREFIX_ . 'product_comment` pc
			LEFT JOIN `' . _DB_PREFIX_ . 'customer` c ON (c.`id_customer` = pc.`id_customer`)';

        if (!$skip_validate) {
            $sql .= ' WHERE pc.`validate` = ' . (int) $validate;
        }

        $sql .= ' ORDER BY pc.`date_add` DESC';

        if ($p && $limit) {
            $offset = ($p - 1) * $limit;
            $sql .= ' LIMIT ' . (int) $offset . ',' . (int) $limit;
        }

        return Db::getInstance()->executeS($sql);
    }

    public static function getCountByValidate($validate = '0', $skip_validate = false)
    {
        $sql = '
            SELECT COUNT(*)
            FROM `' . _DB_PREFIX_ . 'product_comment`';

        if (!$skip_validate) {
            $sql .= ' WHERE `validate` = ' . (int) $validate;
        }

        return (int) Db::getInstance()->getValue($sql);
    }


}