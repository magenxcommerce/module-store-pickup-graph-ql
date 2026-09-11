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
     *
     * The field-array renderer hands the column definition to the renderer
     * block (setColumn), but — unlike the plain text cells it builds itself —
     * does nothing with the column's `class`. Apply it here so
     * `'class' => 'required-entry'` on the country column validates the
     * <select> like any other required admin field.
     */
    public function _toHtml()
    {
        if (!$this->getOptions()) {
            // Not a multiselect, so the source prepends a blank
            // "--Please Select--" option: required-entry rejects exactly that.
            $this->setOptions($this->countrySource->toOptionArray());
        }

        $column = $this->getColumn();
        if (is_array($column) && !empty($column['class']) && !$this->getClass()) {
            $this->setClass((string) $column['class']);
        }

        return parent::_toHtml();
    }
}
