<?php
/**
 * 2007-2016 PrestaShop
 *
 * Thirty Bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017 Thirty Bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://www.thirtybees.com for more information.
 *
 * @author    Thirty Bees <contact@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017 Thirty Bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *  PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

/**
 * Class HTMLTemplateSupplyOrderFormCore
 *
 * @since 1.0.0
 */
class HTMLTemplateSupplyOrderFormCore extends HTMLTemplate
{
    // @codingStandardsIgnoreStart
    /** @var SupplyOrder $supply_order */
    public $supply_order;

    /** @var Warehouse $warehouse */
    public $warehouse;

    /** @var Address $address_warehouse */
    public $address_warehouse;

    /** @var Address $address_supplier */
    public $address_supplier;

    /** @var Context $context */
    public $context;
    // @codingStandardsIgnoreEnd

    /**
     * @param SupplyOrder $supplyOrder
     * @param Smarty      $smarty
     *
     * @throws PrestaShopException
     */
    public function __construct(SupplyOrder $supplyOrder, Smarty $smarty)
    {
        $this->supply_order = $supplyOrder;
        $this->smarty = $smarty;
        $this->context = Context::getContext();
        $this->warehouse = new Warehouse((int) $supplyOrder->id_warehouse);
        $this->address_warehouse = new Address((int) $this->warehouse->id_address);
        $this->address_supplier = new Address(Address::getAddressIdBySupplierId((int) $supplyOrder->id_supplier));

        // header informations
        $this->date = Tools::displayDate($supplyOrder->date_add);
        $this->title = HTMLTemplateSupplyOrderForm::l('Supply order form');

        $this->shop = new Shop((int) $this->order->id_shop);
    }

    /**
     * @see     HTMLTemplate::getContent()
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public function getContent()
    {
        $supplyOrderDetails = $this->supply_order->getEntriesCollection((int) $this->supply_order->id_lang);
        $this->roundSupplyOrderDetails($supplyOrderDetails);

        $this->roundSupplyOrder($this->supply_order);

        $taxOrderSummary = $this->getTaxOrderSummary();
        $currency = new Currency((int) $this->supply_order->id_currency);

        $this->smarty->assign(
            [
                'warehouse'            => $this->warehouse,
                'address_warehouse'    => $this->address_warehouse,
                'address_supplier'     => $this->address_supplier,
                'supply_order'         => $this->supply_order,
                'supply_order_details' => $supplyOrderDetails,
                'tax_order_summary'    => $taxOrderSummary,
                'currency'             => $currency,
            ]
        );

        $tpls = [
            'style_tab'     => $this->smarty->fetch($this->getTemplate('invoice.style-tab')),
            'addresses_tab' => $this->smarty->fetch($this->getTemplate('supply-order.addresses-tab')),
            'product_tab'   => $this->smarty->fetch($this->getTemplate('supply-order.product-tab')),
            'tax_tab'       => $this->smarty->fetch($this->getTemplate('supply-order.tax-tab')),
            'total_tab'     => $this->smarty->fetch($this->getTemplate('supply-order.total-tab')),
        ];
        $this->smarty->assign($tpls);

        return $this->smarty->fetch($this->getTemplate('supply-order'));
    }

    /**
     * Returns the invoice logo
     *
     * @return String Logo path
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    protected function getLogo()
    {
        $logo = '';

        if (Configuration::get('PS_LOGO_INVOICE', null, null, (int) Shop::getContextShopID()) != false && file_exists(_PS_IMG_DIR_.Configuration::get('PS_LOGO_INVOICE', null, null, (int) Shop::getContextShopID()))) {
            $logo = _PS_IMG_DIR_.Configuration::get('PS_LOGO_INVOICE', null, null, (int) Shop::getContextShopID());
        } elseif (Configuration::get('PS_LOGO', null, null, (int) Shop::getContextShopID()) != false && file_exists(_PS_IMG_DIR_.Configuration::get('PS_LOGO', null, null, (int) Shop::getContextShopID()))) {
            $logo = _PS_IMG_DIR_.Configuration::get('PS_LOGO', null, null, (int) Shop::getContextShopID());
        }

        return $logo;
    }

    /**
     * @see HTMLTemplate::getBulkFilename()
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public function getBulkFilename()
    {
        return 'supply_order.pdf';
    }

    /**
     * @see HTMLTemplate::getFileName()
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public function getFilename()
    {
        return self::l('SupplyOrderForm').sprintf('_%s', $this->supply_order->reference).'.pdf';
    }

    /**
     * Get order taxes summary
     *
     * @return array|false|mysqli_result|null|PDOStatement|resource
     * @throws PrestaShopDatabaseException
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    protected function getTaxOrderSummary()
    {
        $query = new DbQuery();
        $query->select(
            '
			SUM(price_with_order_discount_te) as base_te,
			tax_rate,
			SUM(tax_value_with_order_discount) as total_tax_value
		'
        );
        $query->from('supply_order_detail');
        $query->where('id_supply_order = '.(int) $this->supply_order->id);
        $query->groupBy('tax_rate');

        $results = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($query);

        foreach ($results as &$result) {
            $result['base_te'] = Tools::ps_round($result['base_te'], 2);
            $result['tax_rate'] = Tools::ps_round($result['tax_rate'], 2);
            $result['total_tax_value'] = Tools::ps_round($result['total_tax_value'], 2);
        }

        unset($result); // remove reference

        return $results;
    }

    /**
     * @see HTMLTemplate::getHeader()
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public function getHeader()
    {
        $shopName = Configuration::get('PS_SHOP_NAME');
        $pathLogo = $this->getLogo();
        $width = $height = 0;

        if (!empty($pathLogo)) {
            list($width, $height) = getimagesize($pathLogo);
        }

        $this->smarty->assign(
            [
                'logo_path'       => $pathLogo,
                'img_ps_dir'      => 'http://'.Tools::getMediaServer(_PS_IMG_)._PS_IMG_,
                'img_update_time' => Configuration::get('PS_IMG_UPDATE_TIME'),
                'title'           => $this->title,
                'reference'       => $this->supply_order->reference,
                'date'            => $this->date,
                'shop_name'       => $shopName,
                'width_logo'      => $width,
                'height_logo'     => $height,
            ]
        );

        return $this->smarty->fetch($this->getTemplate('supply-order-header'));
    }

    /**
     * @see HTMLTemplate::getFooter()
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    public function getFooter()
    {
        $this->address = $this->address_warehouse;
        $freeText = [];
        $freeText[] = HTMLTemplateSupplyOrderForm::l('TE: Tax excluded');
        $freeText[] = HTMLTemplateSupplyOrderForm::l('TI: Tax included');

        $this->smarty->assign(
            [
                'shop_address' => $this->getShopAddress(),
                'shop_fax'     => Configuration::get('PS_SHOP_FAX'),
                'shop_phone'   => Configuration::get('PS_SHOP_PHONE'),
                'shop_details' => Configuration::get('PS_SHOP_DETAILS'),
                'free_text'    => $freeText,
            ]
        );

        return $this->smarty->fetch($this->getTemplate('supply-order-footer'));
    }

    /**
     * Rounds values of a SupplyOrderDetail object
     *
     * @param array|PrestaShopCollection $collection
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    protected function roundSupplyOrderDetails(&$collection)
    {
        foreach ($collection as $supplyOrderDetail) {
            /** @var SupplyOrderDetail $supplyOrderDetail */
            $supplyOrderDetail->unit_price_te = Tools::ps_round($supplyOrderDetail->unit_price_te, 2);
            $supplyOrderDetail->price_te = Tools::ps_round($supplyOrderDetail->price_te, 2);
            $supplyOrderDetail->discount_rate = Tools::ps_round($supplyOrderDetail->discount_rate, 2);
            $supplyOrderDetail->price_with_discount_te = Tools::ps_round($supplyOrderDetail->price_with_discount_te, 2);
            $supplyOrderDetail->tax_rate = Tools::ps_round($supplyOrderDetail->tax_rate, 2);
            $supplyOrderDetail->price_ti = Tools::ps_round($supplyOrderDetail->price_ti, 2);
        }
    }

    /**
     * Rounds values of a SupplyOrder object
     *
     * @param SupplyOrder $supplyOrder
     *
     * @since   1.0.0
     * @version 1.0.0 Initial version
     */
    protected function roundSupplyOrder(SupplyOrder &$supplyOrder)
    {
        $supplyOrder->total_te = Tools::ps_round($supplyOrder->total_te, 2);
        $supplyOrder->discount_value_te = Tools::ps_round($supplyOrder->discount_value_te, 2);
        $supplyOrder->total_with_discount_te = Tools::ps_round($supplyOrder->total_with_discount_te, 2);
        $supplyOrder->total_tax = Tools::ps_round($supplyOrder->total_tax, 2);
        $supplyOrder->total_ti = Tools::ps_round($supplyOrder->total_ti, 2);
    }
}
