<?php

namespace IABTcf\Utils;

use DateTime;
use IABTcf\Definitions;
use IABTcf\Exceptions\InvalidConsentStringException;
use IABTcf\Exceptions\InvalidEncodingTypeException;
use IABTcf\Exceptions\InvalidSegmentException;
use IABTcf\Exceptions\InvalidVersionException;
use IABTcf\Field;

class Bits
{
    private const NEW_POSITION = 'newPosition';
    private const FIELD_VALUE = 'fieldValue';
    private const DASH = "-";
    private const PLUS = "+";
    private const SLASH = "/";
    private const UNDERSCORE = "_";
    private const EQUAL = '=';
    private const EMPTY_STR = '';
    private const INT = 'int';
    private const BOOL = 'bool';
    private const DATE = 'date';
    private const BITS = 'bits';
    private const LIST_TYPE = 'list';
    private const LANGUAGE = 'language';
    private const NEGATIVE_BIT = '0';

    private static $padLeftCalls = [];

    /**
	 * @param $bitString
	 * @return array
	 */
	public static function decodeBitsToIds(string $bitString): array
	{
		$index = 0;
		$bitExploded = str_split($bitString, 1);

        $ids = [];
        $bitExplodedLength = count($bitExploded);
        for ($i = 0; $i< $bitExplodedLength; $i++) {
            $currentBit = $bitExploded[$i];
            $nextIndex = $index + 1;
            if ($currentBit === '1') {
                $ids[] = $nextIndex;
            }
            $index++;
        }

        return $ids;
	}

	/**
	 * @param  string $bitString
	 * @param  int $start
	 * @param  int $length
	 * @return DateTime
	 */
	private static function decodeBitsToDate(string $bitString, int $start, int $length): DateTime
	{
		$date = new DateTime;
		$date->setTimestamp(self::decodeBitsToInt($bitString, $start, $length) / 10);

		return $date;
	}

	/**
	 * @param  string $bitString
	 * @return string
	 */
	private static function decodeBitsToLetter(string $bitString): string
	{
		$letterCode = self::decodeBitsToInt($bitString);

		return strtolower(chr($letterCode + 65));
	}

	/**
	 * @param  string $bitString
	 * @param  int $start
	 * @return bool
	 */
	private static function decodeBitsToBool(string $bitString, int $start): bool
	{
		return intval(substr($bitString, $start, 1), 2) === 1;
	}

	/**
	 * @param  string  $string
	 * @param  int     $leftPadding
	 * @return string
	 */
	public static function padLeft(string $string, int $leftPadding): string
	{
	    $padLeftCallKey = "$string-$leftPadding";
	    if (isset(static::$padLeftCalls[$padLeftCallKey])) {
            $padLeftCall = static::$padLeftCalls[$padLeftCallKey];
        } else {
            $padding = self::validPadding($leftPadding);
            $padLeftCall = str_repeat(self::NEGATIVE_BIT, $padding) . $string;
            static::$padLeftCalls[$padLeftCallKey] = $padLeftCall;
        }
        return $padLeftCall;
	}

	/**
	 * @param  string  $string
	 * @param  int     $rightPadding
	 * @return string
	 */
	public static function padRight(string $string, int $rightPadding): string
	{
        $padding = self::validPadding($rightPadding);
		return $string . str_repeat(self::NEGATIVE_BIT, $padding);
	}

    /**
     * @param  int  $padding
     * @return int
     */
	private static function validPadding(int $padding): int
    {
        return $padding < 0 ? 0 : $padding;
    }

	/**
	 * @param string $bitString
	 * @param int $start
	 * @param int $length
	 * @return int
	 */
	private static function decodeBitsToInt(string $bitString, int $start = 0, int $length = 0): int
	{
		if ($start === 0 && $length === 0) {
			return intval($bitString, 2);
		}

		return intval(substr($bitString, $start, $length), 2);
	}

    /**
     * @param  int       $number
     * @param  int|null  $numBits
     * @return string
     */
	private static function encodeIntToBits(int $number, int $numBits = null): string
	{
		$bitString = self::EMPTY_STR;
		if (is_numeric($number)) {
			$bitString = decbin(intval($number, 10));
		}
		// Pad the string if not filling all bits
		if (! is_null($numBits) && $numBits >= strlen($bitString)) {
			$bitString = self::padLeft($bitString, $numBits - strlen($bitString));
		}
		// Truncate the string if longer than the number of bits
		if (! is_null($numBits) && strlen($bitString) > $numBits) {
			$bitString = substr($bitString, 0, $numBits);
		}

		return $bitString;
	}

	/**
	 * @param  bool $value
	 * @return string
	 */
	private static function encodeBoolToBits(bool $value): string
	{
		return self::encodeIntToBits($value === true ? 1 : 0, 1);
	}

    /**
     * @param  DateTime  $date
     * @param  int|null  $numBits
     * @return string
     */
	private static function encodeDateToBits(DateTime $date, int $numBits = null): string
	{
		return self::encodeIntToBits($date->getTimestamp() * 10, $numBits);
	}

	/**
	 * @param  string $letter
	 * @param  int    $numBits
	 * @return string
	 */
	private static function encodeLetterToBits(string $letter, int $numBits = null): string
	{
		$upperLetter = strtoupper($letter);
		return self::encodeIntToBits(ord($upperLetter[0]) - 65, $numBits);
	}

	/**
	 * @param  string $language
	 * @param  int    $numBits
	 * @return string
	 */
	private static function encodeLanguageToBits(string $language, int $numBits = 12): string
	{
		return self::encodeLetterToBits(substr($language, 0, 1), $numBits / 2) . self::encodeLetterToBits(substr($language, 1), $numBits / 2);
	}

	/**
	 * @param  string $string
	 * @return string
	 * @throws InvalidConsentStringException
	 */
	public static function decodeFromBase64(string $string): string
	{
		// add padding
        $padding = 4 - strlen($string) % 4;
        if ($padding < 4) {
            $string = str_pad($string, $padding, $padding);
        }

		// replace unsafe characters
		$string = str_replace(self::DASH, self::PLUS, $string);
		$string = str_replace(self::UNDERSCORE, self::SLASH, $string);

		$bytes = base64_decode($string, true);
		if ($bytes === false) {
			throw new InvalidConsentStringException();
		}
		$inputBits = self::EMPTY_STR;
		$bytesLength = strlen($bytes);
		for ($i = 0; $i < $bytesLength; $i++) {
			$bitString = decbin(ord($bytes[$i]));
			$leftPadding = 8 - strlen($bitString);
			$inputBits .= self::padLeft($bitString, $leftPadding);

		}
		return $inputBits;
	}

	/**
	 * @param  string $bitString
	 * @return int
	 * @throws InvalidVersionException
	 */
	public static function extractVersion(string $bitString): int {
		$version = self::decodeBitsToInt($bitString, 0, Definitions::getVersionNumBits());
		if (! is_int($version)) {
			throw new InvalidVersionException();
		}

		return $version;
	}

	/**
	 * @param  string $bitString
	 * @return int
	 * @throws InvalidSegmentException
	 */
	public static function extractSegment(string $bitString): int {
		$segmentId = self::decodeBitsToInt($bitString, 0, Definitions::getSegmentNumBits());
		if (! is_int($segmentId)) {
			throw new InvalidSegmentException();
		}

		return $segmentId;
	}

	/**
	 * @param string $bitString
	 * @param array $definitionMap
	 * @return array
	 */
	public static function decodeConsentStringBitValue(string $bitString, array $definitionMap): array
	{
		$decodedObject = self::decodeFields($bitString, $definitionMap['fields']);
		unset($decodedObject[self::NEW_POSITION]);

		return $decodedObject;
	}

	/**
	 * @param array $input
	 * @param Field $field
	 * @param bool $validate
	 * @return string
	 * @throws InvalidEncodingTypeException
	 */
	public static function encodeField(array $input, Field $field, bool $validate = true): string
	{
		if ($validate && (! $field->getValidator()($input))) {
			return self::EMPTY_STR;
		}
		$bitCount = $field->getNumBits()($input);
		$inputValue = $input[$field->getName()];
		$fieldValue = is_null($inputValue) ? self::EMPTY_STR : $inputValue;
		switch ($field->getType()) {
			case self::INT:
				return self::encodeIntToBits($fieldValue, $bitCount);
			case self::BOOL:
				return self::encodeBoolToBits($fieldValue);
			case self::DATE:
				return self::encodeDateToBits($fieldValue, $bitCount);
			case self::BITS:
				return substr(self::padRight($fieldValue, $bitCount - strlen($fieldValue)), 0, $bitCount);
			case self::LIST_TYPE:
				$reduce = function ($acc, $listValue) use ($field) {
					return $acc . self::encodeFields($listValue, $field->getFields());
				};
				return array_reduce($fieldValue, $reduce, self::EMPTY_STR);
			case self::LANGUAGE:
				return self::encodeLanguageToBits($fieldValue, $bitCount);
			default:
				throw new InvalidEncodingTypeException();
		}
	}

	/**
	 * @param array $input
	 * @param array $fields
	 * @return string
	 */
	private static function encodeFields(array $input, $fields): string
	{
		$reduce = function (string $acc, Field $field) use ($input) {
			return $acc . self::encodeField($input, $field);
		};

		return array_reduce($fields, $reduce, self::EMPTY_STR);
	}

	public static function encodeBitStringToBase64(string $bitString): string {
		// Pad length to multiple of 8
		$paddedBinaryValue = self::padRight($bitString, 7 - ((strlen($bitString) + 7) % 8));
		$bytes = self::EMPTY_STR;
		for ($i = 0; $i < strlen($paddedBinaryValue); $i += 8) {
			$bytes .= chr(intval(substr($paddedBinaryValue, $i, 8), 2));
		}
		// Make base64 string URL friendly
		return self::urlSafeB64Encode($bytes);
	}
	/**
	 * @param string $bytes
	 * @return string
	 */
	private static function urlSafeB64Encode(string $bytes): string {
		return str_replace(
			self::PLUS,
            self::DASH,
			str_replace(
				self::SLASH,
                self::UNDERSCORE,
				rtrim(base64_encode($bytes), self::EQUAL)
			)
		);
	}

	/**
	 * @param string $bitString
	 * @param array $output
	 * @param int $startPosition
	 * @param Field $field
	 * @return array
	 * @throws InvalidEncodingTypeException
	 */
	private static function decodeField(string $bitString, array $output, int $startPosition, Field $field): array
	{
		if (! $field->getValidator()($output)) {
			// Not decoding this field so make sure we start parsing the next field at the same point
			return [self::NEW_POSITION => $startPosition];
		}

		$returnValue = [];
		$bitCount = $field->getNumBits()($output);
		if (! is_null($bitCount)) {
			$returnValue[self::NEW_POSITION] = $startPosition + $bitCount;
		}

		switch ($field->getType()) {
			case self::INT:
				return array_merge([self::FIELD_VALUE => self::decodeBitsToInt($bitString, $startPosition, $bitCount)], $returnValue);
			case self::BOOL:
				return array_merge([self::FIELD_VALUE => self::decodeBitsToBool($bitString, $startPosition)], $returnValue);
			case self::DATE:
				return array_merge([self::FIELD_VALUE => self::decodeBitsToDate($bitString, $startPosition, $bitCount)], $returnValue);
			case self::BITS:
				return array_merge([self::FIELD_VALUE => substr($bitString, $startPosition, $bitCount)], $returnValue);
			case self::LIST_TYPE:
				return array_merge(self::decodeList($bitString, $output, $startPosition, $field), $returnValue);
			case self::LANGUAGE:
				return array_merge([self::FIELD_VALUE => self::decodeBitsToLanguage($bitString, $startPosition, $bitCount)], $returnValue);
			default:
				throw new InvalidEncodingTypeException();
		}
	}

	/**
	 * @param string $bitString
	 * @param array $output
	 * @param int $startPosition
	 * @param Field $field
	 * @return array
	 */
	private static function decodeList(string $bitString, array $output, int $startPosition, Field $field): array
	{
		$listEntryCount = $field->getListCount()($output);
		if (is_null($listEntryCount)) {
			$listEntryCount = 0;
		}

		$fields = $field->getFields();
		$newPosition = $startPosition;
		$fieldValue = [];
		for ($i = 0; $i < $listEntryCount; $i++) {
			$decodedFields = self::decodeFields($bitString, $fields, $newPosition);
			$newPosition = $decodedFields[self::NEW_POSITION];
			unset($decodedFields[self::NEW_POSITION]);
			$fieldValue[] = $decodedFields;
		}

		return [self::FIELD_VALUE => $fieldValue, self::NEW_POSITION => $newPosition];
	}

	/**
	 * @param string $bitString
	 * @param int $start
	 * @param int $length
	 * @return string
	 */
	private static function decodeBitsToLanguage(string $bitString, int $start, int $length): string
	{
		$languageBitString = substr($bitString, $start, $length);

		return self::decodeBitsToLetter(substr($languageBitString, 0, $length / 2)) . self::decodeBitsToLetter(substr($languageBitString, $length / 2));
	}

	/**
	 * @param string $bitString
	 * @param array $fields
	 * @param int $startPosition
	 * @return array
	 */
	private static function decodeFields(string $bitString, array $fields, int $startPosition = 0): array
	{
		$position = $startPosition;

		$decodedObject = [];
		foreach ($fields as $field) {
            $fieldDecoded = self::decodeField($bitString, $decodedObject, $position, $field);
            $fieldValue = isset($fieldDecoded[self::FIELD_VALUE]) ? $fieldDecoded[self::FIELD_VALUE] : null;
            $newPosition = isset($fieldDecoded[self::NEW_POSITION]) ? $fieldDecoded[self::NEW_POSITION] : null;
            if (! is_null($fieldValue)) {
                $decodedObject[$field->getName()] = $fieldValue;
            }
            if (! is_null($newPosition)) {
                $position = $newPosition;
            }
        }
		$decodedObject[self::NEW_POSITION] = $position;

		return $decodedObject;
	}
}
