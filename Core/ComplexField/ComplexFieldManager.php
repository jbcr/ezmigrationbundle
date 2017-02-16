<?php

namespace Kaliop\eZMigrationBundle\Core\ComplexField;

use Kaliop\eZMigrationBundle\API\ComplexFieldInterface;
use Kaliop\eZMigrationBundle\API\FieldValueImporterInterface;
use Kaliop\eZMigrationBundle\API\FieldDefinitionConverterInterface;
use Kaliop\eZMigrationBundle\API\FieldValueConverterInterface;
use eZ\Publish\API\Repository\FieldTypeService;

class ComplexFieldManager
{
    /** @var FieldValueImporterInterface[][] */
    protected $fieldTypeMap;
    protected $fieldTypeService;

    public function __construct(FieldTypeService $fieldTypeService)
    {
        $this->fieldTypeService = $fieldTypeService;
    }

    /**
     * @param FieldValueImporterInterface|ComplexFieldInterface $complexField
     * @param string $fieldTypeIdentifier
     * @param string $contentTypeIdentifier
     * @throws \Exception
     */
    public function addComplexField($complexField, $fieldTypeIdentifier, $contentTypeIdentifier = null)
    {
        // This is purely BC; at some point we will typehint to FieldValueImporterInterface
        if ((!$complexField instanceof ComplexFieldInterface) && (!$complexField instanceof FieldValueImporterInterface)) {
            throw new \Exception("Can not register object of class '" . get_class($complexField) . "' as complex field handler because it does not support the desired interface");
        }

        if ($contentTypeIdentifier == null) {
            $contentTypeIdentifier = '*';
        }
        $this->fieldTypeMap[$contentTypeIdentifier][$fieldTypeIdentifier] = $complexField;
    }

    /**
     * @param string $fieldTypeIdentifier
     * @param string $contentTypeIdentifier
     * @return bool
     */
    public function managesField($fieldTypeIdentifier, $contentTypeIdentifier)
    {
        return (isset($this->fieldTypeMap[$contentTypeIdentifier][$fieldTypeIdentifier]) ||
            isset($this->fieldTypeMap['*'][$fieldTypeIdentifier]));
    }

    /**
     * @param string $fieldTypeIdentifier
     * @param string $contentTypeIdentifier
     * @param mixed $hashValue
     * @param array $context
     * @return mixed
     */
    public function hashToFieldValue($fieldTypeIdentifier, $contentTypeIdentifier, $hashValue, array $context = array())
    {
        if ($this->managesField($fieldTypeIdentifier, $contentTypeIdentifier)) {
            $fieldHandler = $this->getFieldHandler($fieldTypeIdentifier, $contentTypeIdentifier);
            // BC
            if (!$fieldHandler instanceof FieldValueImporterInterface) {
                return $fieldHandler->createValue($hashValue, $context);
            }
            return $fieldHandler->hashToFieldValue($hashValue, $context);
        }

        $fieldType = $this->fieldTypeService->getFieldType($fieldTypeIdentifier);
        return $fieldType->fromHash($hashValue);
    }

    /**
     * @param string $fieldTypeIdentifier
     * @param string $contentTypeIdentifier
     * @param \eZ\Publish\SPI\FieldType\Value $value
     * @param array $context
     * @return mixed
     */
    public function fieldValueToHash($fieldTypeIdentifier, $contentTypeIdentifier, $value, array $context = array())
    {
        if ($this->managesField($fieldTypeIdentifier, $contentTypeIdentifier)) {
            $fieldHandler = $this->getFieldHandler($fieldTypeIdentifier, $contentTypeIdentifier);
            if ($fieldHandler instanceof FieldValueConverterInterface) {
                return $fieldHandler->fieldValueToHash($value, $context);
            }
        }

        $fieldType = $this->fieldTypeService->getFieldType($fieldTypeIdentifier);
        return $fieldType->toHash($value);
    }

    /**
     * @param string $fieldTypeIdentifier
     * @param string $contentTypeIdentifier
     * @return bool
     */
    public function managesFieldDefinition($fieldTypeIdentifier, $contentTypeIdentifier)
    {
        if (!$this->managesField($fieldTypeIdentifier, $contentTypeIdentifier)) {
            return false;
        }

        $fieldHandler = $this->getFieldHandler($fieldTypeIdentifier, $contentTypeIdentifier);
        return ($fieldHandler instanceof FieldDefinitionConverterInterface);
    }

    /**
     * @param string $fieldTypeIdentifier
     * @param string $contentTypeIdentifier
     * @param mixed $fieldSettingsHash
     * @param array $context
     * @return mixed
     */
    public function hashToFieldSettings($fieldTypeIdentifier, $contentTypeIdentifier, $fieldSettingsHash, array $context = array())
    {
        if ($this->managesFieldDefinition($fieldTypeIdentifier, $contentTypeIdentifier)) {
            return $this->getFieldHandler($fieldTypeIdentifier, $contentTypeIdentifier)->hashToFieldSettings($fieldSettingsHash, $context);
        }

        return $fieldSettingsHash;
    }

    /**
     * @param string $fieldTypeIdentifier
     * @param string $contentTypeIdentifier
     * @param mixed $fieldSettings
     * @param array $context
     * @return mixed
     */
    public function fieldSettingsToHash($fieldTypeIdentifier, $contentTypeIdentifier, $fieldSettings, array $context = array())
    {
        if ($this->managesFieldDefinition($fieldTypeIdentifier, $contentTypeIdentifier)) {
            return $this->getFieldHandler($fieldTypeIdentifier, $contentTypeIdentifier)->fieldSettingsToHash($fieldSettings, $context);
        }

        return $fieldSettings;
    }

    /**
     * @param string $fieldTypeIdentifier
     * @param string $contentTypeIdentifier
     * @return FieldValueImporterInterface|ComplexFieldInterface
     * @throws \Exception
     */
    protected function getFieldHandler($fieldTypeIdentifier, $contentTypeIdentifier) {
        if (isset($this->fieldTypeMap[$contentTypeIdentifier][$fieldTypeIdentifier])) {
            return $this->fieldTypeMap[$contentTypeIdentifier][$fieldTypeIdentifier];
        } else if (isset($this->fieldTypeMap['*'][$fieldTypeIdentifier])) {
            return $this->fieldTypeMap['*'][$fieldTypeIdentifier];
        }

        throw new \Exception("No complex field handler registered for field '$fieldTypeIdentifier' in content type '$contentTypeIdentifier'");
    }

    /**
     * @param string $fieldTypeIdentifier
     * @param string $contentTypeIdentifier
     * @param mixed $fieldValue as gotten from a migration definition
     * @param array $context
     * @return mixed as usable in a Content create/update struct
     *
     * @deprecated BC
     */
    public function getComplexFieldValue($fieldTypeIdentifier, $contentTypeIdentifier, $fieldValue, array $context = array())
    {
        return $this->hashToFieldValue($fieldTypeIdentifier, $contentTypeIdentifier, $fieldValue, $context);
    }
}
