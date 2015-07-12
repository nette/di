<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2015 Petr Bilek (http://ww.sallyx.org)
 */

namespace Nette\DI\Config\Adapters;

use Nette;
use Nette\DI\Config\Helpers;
use Nette\DI\Statement;

/**
 * Reading and generating XML files.
 */
class XmlAdapter extends Nette\Object implements Nette\DI\Config\IAdapter
{

	const NS = "http://www.nette.org/xmlns/nette/config/1.0";

	/**
	 * Reads configuration from XML file.
	 * @param  string  file name
	 * @return array
	 */
	public function load($file)
	{
		$options = LIBXML_NOBLANKS | LIBXML_NOCDATA | LIBXML_NOENT | LIBXML_NSCLEAN; // | LIBXML_PEDANTIC ?
		$parserClass = "\\Nette\\DI\\Config\\Adapters\\XMLElementParser";
		$document = simplexml_load_file($file, $parserClass, $options, self::NS);
		return $document->parse();
	}

	/**
	 * Generates configuration in XML format.
	 * @return string
	 */
	public function dump(array $data)
	{
		$writer = new XmlElementWriter('<config/>', LIBXML_NOEMPTYTAG, false, self::NS, "nc");
		$writer->addAttribute('xmlns:xmlns:nc', self::NS);
		$writer->addAttribute('xmlns', self::NS);
		$writer->addConfig($data);
		return $writer->asXML();
	}

}

/**
 * This class helps to parse XML config to PHP array
 * @internal
 */
class XMLElementParser extends \SimpleXMLElement
{

	/** Names of attributes */
	const ATTR_ARRAY = 'array',
		ATTR_BOOL = 'bool',
		ATTR_NUMBER = 'number',
		ATTR_DELIMITER = 'delimiter',
		ATTR_EXTENDS = 'extends',
		ATTR_MERGING = 'merging',
		ATTR_NULL = 'null',
		ATTR_STATEMENT = 'statement',
		ATTR_SPACE = 'space';

	/** Values of attributes */
	const ATTR_MERGING_REPLACE = 'replace',
		ATTR_ARRAY_ASSOCIATIVE = 'associative',
		ATTR_ARRAY_NUMERIC = 'numeric',
		ATTR_ARRAY_STRING = 'string',
		ATTR_NULL_VALUE = 'null',
		ATTR_SPACE_PRESERVE = 'preserve',
		DEFAULT_DELIMITER = ',';

	/**
	 * Parse DI config
	 *
	 * @return array|string|NULL|Statement
	 */
	public function parse()
	{
		$statement = $this->getAttribute(self::ATTR_STATEMENT);
		if (!empty($statement)) {
			if ($statement === self::ATTR_STATEMENT) {
				return $this->parseStatements($statement);
			}
			$res = $this->getNeonAdapter()->load('data://text/plain,- ' . $statement);
			return array_shift($res);
		}

		$res = $this->parseChildren();
		$extends = $this->getAttribute(self::ATTR_EXTENDS);
		$merging = $this->getattribute(self::ATTR_MERGING);
		if ($merging === self::ATTR_MERGING_REPLACE && !is_array($res)) {
			$name = $this->getName();
			throw new Nette\InvalidStateException("Replacing operator is available only for arrays, element '$name' is not array");
		}
		if (!empty($extends)) {
			$res[Helpers::EXTENDS_KEY] = $extends;
		} elseif ($merging === self::ATTR_MERGING_REPLACE) {
			$res[Helpers::EXTENDS_KEY] = Helpers::OVERWRITE;
		}
		return $res;
	}

	/**
	 * Returns element value
	 *
	 * If element has a bool attribute, returns bool value of this attribute.
	 * If element has a null attribute, returns NULL;
	 * Othervise returns string value of this element.
	 *
	 * @return string|bool|NULL
	 */
	public function getValue()
	{
		if ($this->getAttribute(self::ATTR_NULL)) {
			return NULL;
		}
		$bool = $this->getAttribute(self::ATTR_BOOL);
		if ($bool) {
			switch (strtolower($bool)) {
				case 'yes':
// break intentionally omitted
				case 'true':
// break intentionally omitted
				case 'on':
// break intentionally omitted
				case '1':
					return TRUE;
				default:
					return FALSE;
			}
		}
		$number = $this->getAttribute(self::ATTR_NUMBER);
		if (!is_null($number)) {
			return $number + 0;
		}
		return (string) $this;
	}

	/**
	 * Returns attribute of name $name if exists, or $default value
	 *
	 * @param string $name
	 * @param string $default
	 * @return string|NULL
	 */
	public function getAttribute($name, $default = NULL)
	{
		$attrs = $this->attributes();
		$attr = $attrs[$name];
		if (is_null($attr)) {
			return $default;
		}
		return (string) $attr;
	}

	/**
	 * @staticvar type $neonAdapter
	 * @return type
	 */
	private function getNeonAdapter()
	{
		static $neonAdapter;
		$neonAdapter = $neonAdapter ? : new NeonAdapter();
		return $neonAdapter;
	}

	/**
	 * Parse element with attribute statement (list of statements)
	 *
	 * @param string $statements
	 * @return Statement
	 */
	private function parseStatements($statements)
	{
		$res = null;
		foreach ($this->children() as $statement) {
			list($entity, $arguments) = $this->parseStatement($statement);
			if (!$res) {
				$res = new Statement($entity, $arguments);
			} else {
				$res = new Statement([$res, $entity], $arguments);
			}
		}

		return $res;
	}

	/**
	 * Parse one statement from statement list
	 * @param \SimpleXmlElement $child
	 * @return array  [0 => $entity, 1 => $attributes]
	 */
	private function parseStatement($child)
	{
		$entityElement = $child->ent;
		if (empty($entityElement)) {
			throw new \Nette\InvalidStateException("Expected <ent> element in statement " . $this->asXML());
		}
		$entity = $child->ent->getValue();
		if (!is_string($entity) or $entity === '') {
			throw new \Nette\InvalidStateException("Element <ent> must have a non-empty string value.");
		}

		$argsElement = $child->args;
		if (empty($argsElement)) {
			return [$entity, NULL];
		}
		$arrayAttr = $argsElement[0]->getAttribute(self::ATTR_ARRAY);
		if (is_null($arrayAttr)) {
			$argsElement[0]->addAttribute(self::ATTR_ARRAY, self::ATTR_ARRAY_NUMERIC);
		}

		$attributes = $argsElement[0]->parseChildren();
		if (!is_array($attributes)) {
			$attributes = [$attributes];
		}
		return [$entity, $attributes];
	}

	/**
	 * Parse children elements or value of this element
	 *
	 * @return array|string|NULL|Nette\DI\Statement
	 */
	private function parseChildren()
	{
		$arrayType = $this->getAttribute(self::ATTR_ARRAY, self::ATTR_ARRAY_ASSOCIATIVE);
		$space = (string) $this->getAttribute(self::ATTR_SPACE);
		if ($space !== self::ATTR_SPACE_PRESERVE and ! empty($space)) {
			throw new Nette\InvalidStateException("Attribute " . self::ATTR_SPACE . " has an unknown value '$space'");
		}

		if ($arrayType === self::ATTR_ARRAY_STRING) {
			$res = $this->parseStringArray();
			$this->trim($res, $space);
			return $res;
		}

		if (!$this->count()) {
			$res = $this->getValue();
			$this->trim($res, $space);
			return $res;
		}

		$res = [];
		switch ($arrayType) {
			default:
			case self::ATTR_ARRAY_ASSOCIATIVE:
				foreach ($this->children() as $key => $child) {
					if (isset($res[$key])) {
						throw new Nette\InvalidStateException("Duplicated key '$key'.");
					}
					$res[$key] = $child->parse();
				}
				break;
			case self::ATTR_ARRAY_NUMERIC:
				foreach ($this->children() as $key => $child) {
					$res[] = $child->parse();
				}
				break;
		}

		return $res;
	}

	/**
	 * @param mixed $value
	 * @param string $space
	 */
	private function trim(&$value, $space)
	{
		if ($space === self::ATTR_SPACE_PRESERVE) {
			return;
		}
		if (is_array($value)) {
			array_walk(
				$value, function(& $val) {
				if (is_string($val)) {
					$val = trim($val);
				}
			}
			);
			return;
		}

		if (is_string($value)) {
			$value = trim($value);
		}
	}

	/**
	 * Parse value of this element with attribute array="string"
	 *
	 * @return array
	 */
	private function parseStringArray()
	{
		if ($this->count() > 0) {
			throw new Nette\InvalidStateException('Element with attribute array="string" can\'t have children: ' . $this->asXML());
		}

		$value = $this->getValue();
		if (is_null($value)) {
			return [];
		}

		$delimiter = $this->getAttribute(self::ATTR_DELIMITER, self::DEFAULT_DELIMITER);
		return explode($delimiter[0], $value);
	}

}

/**
 * This class helps to save array into XML configuration
 * @internal
 */
class XMLElementWriter extends \SimpleXMLElement
{
	/** Entity names */
	const ENT_ITEM = 'item',
		ENT_STATEMENT = 's',
		ENT_STATEMENT_ENTITY = 'ent',
		ENT_STATEMENT_ARGUMENTS = 'args';

	/**
	 * Construct XML DOM from config array
	 * @param array $config
	 */
	public function addConfig(array $config, $element = NULL)
	{
		if ($element === NULL) {
			$element = $this;
		}

		if (isset($config[Helpers::EXTENDS_KEY])) {
			$section = $config[Helpers::EXTENDS_KEY];
			if ($section === Helpers::OVERWRITE) {
				$element->addAttribute(XMLElementParser::ATTR_MERGING, XMLElementParser::ATTR_MERGING_REPLACE);
			} else {
				$element->addAttribute(XMLElementParser::ATTR_EXTENDS, $section);
			}
			unset($config[Helpers::EXTENDS_KEY]);
		}

		$isNumeric = array_keys($config) === range(0, count($config) - 1);
		if ($isNumeric) {
			$element->addAttribute(XMLElementParser::ATTR_ARRAY, XMLElementParser::ATTR_ARRAY_NUMERIC);
		}
		foreach ($config as $name => $value) {
			$name = $isNumeric ? self::ENT_ITEM : $name;
			$this->addElement($element, $name, $value);
		}
	}

	/**
	 * @param \SimpleXmlElement $element
	 * @param string $name
	 * @param mixed $value
	 * @return void
	 */
	private function addElement(\SimpleXMLElement $element, $name, $value)
	{
		if (is_string($value)) {
			$element->addChild($name, $value);
			return;
		}
		if ($value instanceof Statement) {
			$this->addStatementList($element, $name, $value);
			return;
		}
		$child = $element->addChild($name);
		if ($value === NULL) {
			$child->addAttribute(XMLElementParser::ATTR_NULL, XMLElementParser::ATTR_NULL_VALUE);
		} elseif (is_bool($value)) {
			$child->addAttribute(XMLElementParser::ATTR_BOOL, $value ? '1' : '0');
		} elseif (is_numeric($value)) {
			$child->addAttribute(XMLElementParser::ATTR_NUMBER, $value);
		} elseif (is_array($value)) {
			$this->addConfig($value, $child);
		} else {
			throw new \Nette\InvalidStateException("Unsupported type " . gettype($value));
		}
	}

	/**
	 * @param \SimpleXMLElement $element
	 * @param string $name
	 * @param \Statement $value
	 * @return void
	 */
	private function addStatementList(\SimpleXMLElement $element, $name, Statement $value)
	{
		$child = $element->addChild($name);
		$child->addAttribute(XMLElementParser::ATTR_STATEMENT, XMLElementParser::ATTR_STATEMENT);
		$this->addStatement($child, $value);
	}

	/**
	 *
	 * @param \SimpleXMLElement $list
	 * @param Statement $value
	 * @return void
	 * @throws Nette\InvalidStateException
	 */
	private function addStatement(\SimpleXMLElement $list, Statement $value)
	{
		if (!is_array($value->entity)) {
			$child = $list->addChild(self::ENT_STATEMENT);
			$child->addChild(self::ENT_STATEMENT_ENTITY, $value->entity);
			$args = $child->addChild(self::ENT_STATEMENT_ARGUMENTS);
			$this->addConfig($value->arguments, $args);
			return;
		}

		if (!($value->entity[0] instanceof Statement)) {
			throw new Nette\InvalidStateException("Unsupported statement state");
		}
		$this->addStatement($list, $value->entity[0]);
		$this->addStatement($list, new Statement($value->entity[1], $value->arguments));
	}

}
