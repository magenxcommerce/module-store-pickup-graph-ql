<?php
/**
 * Copyright © Magenx. All rights reserved.
 */
declare(strict_types=1);

namespace Magenx\StorePickupGraphQl\Block\Adminhtml\Form\Field;

use Magento\Directory\Model\Config\Source\Country;
use Magento\Framework\View\Element\Context;
use Magento\Framework\View\Element\Html\Select;

/**
 * Country <select> rendered inside the store-locations admin grid.
 */
class CountryColumn extends Select
{
    /**
     * @param Context $context
     * @param Country $countrySource
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly Country $countrySource,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Set the element name (called by the FieldArray renderer).
     *
     * @param string $value
     * @return $this
     */
    public function setInputName($value)
    {
        return $this->setName($value);
    }

    /**
     * Set the element id (called by the FieldArray renderer).
     *
     * @param string $value
     * @return $this
     */
    public function setInputId($value)
    {
        return $this->setId($value);
    }

    /**
     * @inheritDoc
     */
    public function _toHtml()
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->countrySource->toOptionArray());
        }

        return parent::_toHtml();
    }
}
