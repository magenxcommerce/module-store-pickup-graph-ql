<?php
/**
 * Copyright © Magenx. All rights reserved.
 */
declare(strict_types=1);

namespace Magenx\StorePickupGraphQl\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\BlockInterface;

/**
 * Admin repeatable-row grid for configuring physical store pickup locations.
 *
 * The rows are serialized by the {@see \Magenx\StorePickupGraphQl\Model\Config\Backend\Locations}
 * backend model configured on the field — core ArraySerialized plus a required
 * country — and read back by {@see \Magenx\StorePickupGraphQl\Model\Config}.
 */
class Locations extends AbstractFieldArray
{
    private ?CountryColumn $countryRenderer = null;

    /**
     * Use the grouped, wrapping layout instead of the stock single-wide-row
     * table so the 11 per-store fields fit inside the admin config column.
     *
     * @return void
     */
    protected function _construct(): void
    {
        parent::_construct();
        $this->setTemplate('Magenx_StorePickupGraphQl::system/config/form/field/store-locations.phtml');
    }

    /**
     * @inheritDoc
     */
    protected function _prepareToRender(): void
    {
        // No inline widths: the grouped store-locations.phtml layout sizes each
        // cell via CSS flex tracks (view/adminhtml/web/css/store-locations.css).
        $this->addColumn('code', [
            'label' => __('Code'),
            'class' => 'required-entry',
        ]);
        $this->addColumn('name', [
            'label' => __('Name'),
            'class' => 'required-entry',
        ]);
        $this->addColumn('street', [
            'label' => __('Street'),
        ]);
        $this->addColumn('city', [
            'label' => __('City'),
        ]);
        $this->addColumn('region', [
            'label' => __('Region'),
        ]);
        $this->addColumn('postcode', [
            'label' => __('Postcode'),
        ]);
        // Required: an order collected from a location without a country ends
        // up with an incomplete shipping address — see the country_id note on
        // \Magenx\StorePickupGraphQl\Model\Config\Backend\Locations, which
        // enforces the same rule on every save path. CountryColumn applies the
        // class to the rendered <select>.
        $this->addColumn('country_id', [
            'label' => __('Country'),
            'class' => 'required-entry',
            'renderer' => $this->getCountryRenderer(),
        ]);
        $this->addColumn('phone', [
            'label' => __('Phone'),
        ]);
        $this->addColumn('hours', [
            'label' => __('Hours'),
        ]);
        $this->addColumn('latitude', [
            'label' => __('Latitude'),
        ]);
        $this->addColumn('longitude', [
            'label' => __('Longitude'),
        ]);

        $this->_addAfter = false;
        $this->_addButtonLabel = (string) __('Add Store');
    }

    /**
     * Pre-select the country dropdown for an existing row.
     *
     * @param \Magento\Framework\DataObject $row
     * @return void
     */
    protected function _prepareArrayRow(\Magento\Framework\DataObject $row): void
    {
        $country = $row->getData('country_id');
        $options = [];
        if ($country !== null && $country !== '') {
            $options['option_' . $this->getCountryRenderer()->calcOptionHash($country)] = 'selected="selected"';
        }
        $row->setData('option_extra_attrs', $options);
    }

    /**
     * Lazily build the country column renderer.
     *
     * @return CountryColumn
     * @throws LocalizedException
     */
    private function getCountryRenderer(): CountryColumn
    {
        if ($this->countryRenderer === null) {
            $renderer = $this->getLayout()->createBlock(
                CountryColumn::class,
                '',
                ['data' => ['is_render_to_js_template' => true]]
            );
            if (!$renderer instanceof CountryColumn) {
                throw new LocalizedException(__('Could not build the country column renderer.'));
            }
            $this->countryRenderer = $renderer;
        }

        return $this->countryRenderer;
    }
}
