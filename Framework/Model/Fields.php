<?php

namespace Framework\Model\Fields;

/**
 * Django-style Field Base Class
 */
abstract class Field
{
    protected $name;
    protected $nullable = false;
    protected $default = null;
    protected $unique = false;
    protected $dbType;

    public function __construct($name = null, $nullable = false, $default = null)
    {
        $this->name = $name;
        $this->nullable = $nullable;
        $this->default = $default;
    }

    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    public function nullable()
    {
        $this->nullable = true;
        return $this;
    }

    public function default($value)
    {
        $this->default = $value;
        return $this;
    }

    public function unique()
    {
        $this->unique = true;
        return $this;
    }

    abstract public function getSQL($dbType = 'mysql');

    public function toArray()
    {
        return [
            'name' => $this->name,
            'type' => get_class($this),
            'nullable' => $this->nullable,
            'default' => $this->default,
            'unique' => $this->unique,
        ];
    }
}

/**
 * Auto-incrementing ID field
 */
class AutoField extends Field
{
    public function getSQL($dbType = 'mysql')
    {
        if ($dbType === 'sqlite') {
            return "{$this->name} INTEGER PRIMARY KEY AUTOINCREMENT";
        }
        return "`{$this->name}` INT AUTO_INCREMENT PRIMARY KEY";
    }
}

/**
 * String/Char field
 */
class CharField extends Field
{
    private $maxLength = 255;

    public function __construct($name = null, $maxLength = 255, $nullable = false, $default = null)
    {
        parent::__construct($name, $nullable, $default);
        $this->maxLength = $maxLength;
    }

    public function getSQL($dbType = 'mysql')
    {
        $sql = "";
        if ($dbType === 'sqlite') {
            $sql = "{$this->name} TEXT";
        } elseif ($dbType === 'pgsql') {
            $sql = "\"{$this->name}\" VARCHAR({$this->maxLength})";
        } else {
            $sql = "`{$this->name}` VARCHAR({$this->maxLength})";
        }

        if (!$this->nullable)
            $sql .= " NOT NULL";
        if ($this->default !== null)
            $sql .= " DEFAULT '{$this->default}'";
        if ($this->unique)
            $sql .= " UNIQUE";

        return $sql;
    }
}

/**
 * Integer field
 */
class IntegerField extends Field
{
    public function getSQL($dbType = 'mysql')
    {
        $sql = "";
        if ($dbType === 'sqlite') {
            $sql = "{$this->name} INTEGER";
        } elseif ($dbType === 'pgsql') {
            $sql = "\"{$this->name}\" INTEGER";
        } else {
            $sql = "`{$this->name}` INT";
        }

        if (!$this->nullable)
            $sql .= " NOT NULL";
        if ($this->default !== null)
            $sql .= " DEFAULT {$this->default}";

        return $sql;
    }
}

/**
 * Text/Large text field
 */
class TextField extends Field
{
    public function getSQL($dbType = 'mysql')
    {
        $sql = "";
        if ($dbType === 'sqlite') {
            $sql = "{$this->name} TEXT";
        } elseif ($dbType === 'pgsql') {
            $sql = "\"{$this->name}\" TEXT";
        } else {
            $sql = "`{$this->name}` TEXT";
        }

        if ($this->nullable)
            $sql .= " NULL";

        return $sql;
    }
}

/**
 * Boolean field
 */
class BooleanField extends Field
{
    public function getSQL($dbType = 'mysql')
    {
        $default = $this->default !== null ? ($this->default ? 1 : 0) : 0;

        if ($dbType === 'sqlite') {
            $sql = "{$this->name} INTEGER DEFAULT {$default}";
        } elseif ($dbType === 'pgsql') {
            $sql = "\"{$this->name}\" BOOLEAN DEFAULT " . ($default ? 'TRUE' : 'FALSE');
        } else {
            $sql = "`{$this->name}` BOOLEAN DEFAULT {$default}";
        }

        return $sql;
    }
}

/**
 * Float/Decimal field
 */
class FloatField extends Field
{
    private $maxDigits = 10;
    private $decimalPlaces = 2;

    public function __construct($name = null, $maxDigits = 10, $decimalPlaces = 2, $nullable = false, $default = null)
    {
        parent::__construct($name, $nullable, $default);
        $this->maxDigits = $maxDigits;
        $this->decimalPlaces = $decimalPlaces;
    }

    public function getSQL($dbType = 'mysql')
    {
        $sql = "";
        if ($dbType === 'sqlite') {
            $sql = "{$this->name} REAL";
        } elseif ($dbType === 'pgsql') {
            $sql = "\"{$this->name}\" NUMERIC({$this->maxDigits}, {$this->decimalPlaces})";
        } else {
            $sql = "`{$this->name}` DECIMAL({$this->maxDigits}, {$this->decimalPlaces})";
        }

        if (!$this->nullable)
            $sql .= " NOT NULL";
        if ($this->default !== null)
            $sql .= " DEFAULT {$this->default}";

        return $sql;
    }
}

/**
 * DateTime field
 */
class DateTimeField extends Field
{
    public function getSQL($dbType = 'mysql')
    {
        $sql = "";
        if ($dbType === 'sqlite') {
            $sql = "{$this->name} DATETIME";
        } elseif ($dbType === 'pgsql') {
            $sql = "\"{$this->name}\" TIMESTAMP";
        } else {
            $sql = "`{$this->name}` DATETIME";
        }

        if ($this->nullable)
            $sql .= " NULL";

        return $sql;
    }
}

/**
 * Date field
 */
class DateField extends Field
{
    public function getSQL($dbType = 'mysql')
    {
        $sql = "";
        if ($dbType === 'sqlite') {
            $sql = "{$this->name} DATE";
        } elseif ($dbType === 'pgsql') {
            $sql = "\"{$this->name}\" DATE";
        } else {
            $sql = "`{$this->name}` DATE";
        }

        if ($this->nullable)
            $sql .= " NULL";

        return $sql;
    }
}

/**
 * Email field (CharField variant)
 */
class EmailField extends CharField
{
    public function __construct($name = null, $nullable = false, $default = null)
    {
        parent::__construct($name, 255, $nullable, $default);
        $this->unique = true;
    }

    public function getSQL($dbType = 'mysql')
    {
        $sql = parent::getSQL($dbType);
        // Email fields should be unique
        if (strpos($sql, 'UNIQUE') === false) {
            $sql .= " UNIQUE";
        }
        return $sql;
    }
}

/**
 * JSON field
 */
class JSONField extends Field
{
    public function getSQL($dbType = 'mysql')
    {
        $sql = "";
        if ($dbType === 'sqlite') {
            $sql = "{$this->name} TEXT";
        } elseif ($dbType === 'pgsql') {
            $sql = "\"{$this->name}\" JSON";
        } else {
            $sql = "`{$this->name}` JSON";
        }

        if ($this->nullable)
            $sql .= " NULL";

        return $sql;
    }
}

/**
 * Timestamp field (auto-updated)
 */
class TimeStampField extends Field
{
    private $autoNow = false;
    private $autoNowAdd = false;

    public function __construct($name = null, $autoNow = false, $autoNowAdd = false)
    {
        parent::__construct($name, false);
        $this->autoNow = $autoNow;
        $this->autoNowAdd = $autoNowAdd;
    }

    public function autoNow()
    {
        $this->autoNow = true;
        return $this;
    }

    public function autoNowAdd()
    {
        $this->autoNowAdd = true;
        return $this;
    }

    public function getSQL($dbType = 'mysql')
    {
        if ($dbType === 'sqlite') {
            return "{$this->name} DATETIME DEFAULT CURRENT_TIMESTAMP";
        } elseif ($dbType === 'pgsql') {
            return "\"{$this->name}\" TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
        } else {
            return "`{$this->name}` TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
        }
    }
}
