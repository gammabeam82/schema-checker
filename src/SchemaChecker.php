<?php

namespace Gammabeam82\SchemaChecker;

use Gammabeam82\SchemaChecker\Exception\InvalidSchemaException;

/**
 * Class SchemaChecker
 * @package Gammabeam82\SchemaChecker
 */
class SchemaChecker
{
    public const TYPE_DELIMITER = '|';
    public const TYPE_WILDCARD = '*';
    public const TYPE_NULLABLE = 'nullable';

    /**
     * @var string[]
     */
    private $violations = [];

    /**
     * @var string
     */
    private $lastKey;

    /**
     * @param array|string $data
     * @param array $schema
     *
     * @return bool
     * @throws InvalidSchemaException
     */
    public function assertSchema($data, array $schema): bool
    {
        $this->reset();

        if (false === is_array($data)) {
            $data = json_decode($data, true);
        }

        if (null === $data) {
            $this->addInvalidDataViolation(json_last_error_msg());

            return false;
        }

        return $this->check($data, $schema);
    }

    /**
     * @param array $data
     * @param array $schema
     *
     * @return bool
     * @throws InvalidSchemaException
     */
    private function check(array $data, array $schema): bool
    {
        if (0 === count($schema)) {
            throw new InvalidSchemaException();
        }

        if (0 === count($data)) {
            return $this->isNullable($schema);
        }

        if (false !== $this->isIndexed($data)) {
            $data = reset($data);
        }

        if (false === is_array($data) && false !== $this->isIndexed($schema)) {
            $type = gettype($data);
            $expectedType = reset($schema);

            return $this->validateKey($this->lastKey, $type, $expectedType);
        }

        foreach ($schema as $key => $expectedType) {
            if ('string' !== gettype($key) || self::TYPE_NULLABLE === $key) {
                continue;
            }

            if (false === array_key_exists($key, $data)) {
                $this->addMissingKeyViolation($key);
                continue;
            }

            if (false !== is_array($expectedType)) {
                $this->lastKey = $key;
                $this->check($data[$key], $expectedType);
                continue;
            }

            $this->validateKey($key, gettype($data[$key]), $expectedType);
        }

        return 0 === count($this->violations);
    }

    /**
     * @param array $array
     *
     * @return bool
     */
    private function isIndexed(array $array): bool
    {
        return $array === array_values($array);
    }

    /**
     * @param string $type
     * @param string|array $expected
     *
     * @return bool
     */
    private function isIncludes(string $type, $expected): bool
    {
        if (false !== is_array($expected)) {
            return 0 !== count(array_intersect([$type, self::TYPE_WILDCARD], $expected));
        }

        return $type === $expected || self::TYPE_WILDCARD === $expected;
    }

    /**
     * @param string $key
     * @param string $type
     * @param string $expectedType
     *
     * @return bool
     * @throws InvalidSchemaException
     */
    private function validateKey(string $key, string $type, string $expectedType): bool
    {
        if (!preg_match("/^[a-z]{1,}(\|[a-z]{1,})*$/", $expectedType)) {
            throw new InvalidSchemaException();
        }

        $type = 'NULL' === $type ? self::TYPE_NULLABLE : $type;

        if (false !== mb_strpos($expectedType, self::TYPE_DELIMITER)) {
            $match = $this->isIncludes($type, explode(self::TYPE_DELIMITER, $expectedType));
        } else {
            $match = $this->isIncludes($type, $expectedType);
        }

        if (false === $match) {
            $this->addInvalidTypeViolation($key, $type, $expectedType);
        }

        return $match;
    }

    /**
     * @param string|array $schema
     *
     * @return bool
     */
    private function isNullable($schema): bool
    {
        if (false !== is_array($schema)) {
            $nullable = $this->isIndexed($schema) ? mb_strpos(reset($schema), self::TYPE_NULLABLE) : array_key_exists(self::TYPE_NULLABLE, $schema);
        } else {
            $nullable = mb_strpos($schema, self::TYPE_NULLABLE);
        }

        if (false === $nullable) {
            $this->addInvalidDataViolation();
        }

        return $nullable;
    }

    /**
     * @return string
     */
    public function getViolations(): string
    {
        return 0 !== count($this->violations) ? implode(",\n", $this->violations) : '';
    }

    /**
     * @param string $key
     * @param string $type
     * @param string $expected
     */
    private function addInvalidTypeViolation(string $key, string $type, string $expected): void
    {
        $this->violations[] = sprintf("Unexpected type of key: \"%s\". Expected: \"%s\", got: \"%s\"", $key, $expected, $type);
    }

    /**
     * @param string $key
     */
    private function addMissingKeyViolation(string $key): void
    {
        $this->violations[] = sprintf("Key \"%s\" not found.", $key);
    }

    /**
     * @param string|null $message
     */
    private function addInvalidDataViolation(?string $message = null): void
    {
        $message = $message ?? 'Invalid data';
        $this->violations[] = $message;
    }

    private function reset(): void
    {
        $this->violations = [];
        $this->lastKey = null;
    }
}