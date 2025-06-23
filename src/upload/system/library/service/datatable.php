<?php
namespace Service;

use DB;
use Model;

/**
 * class DataTable
 *
 * @property DB $db
 */
abstract class DataTable extends Model
{
    protected $pk = '';
    protected $table = '';
    protected $select_fields = array();
    protected $create_fields = array();
    protected $update_fields = array();
    protected $filter_fields = array();
    protected $order_fields = array();
    protected $date_added = '';
    protected $date_updated = '';

    public function getInfo()
    {
        $result = [
            'pk' => $this->pk,
            'columns' => [],
            'id' => $this->table
        ];

        $sql = "DESCRIBE `" . DB_PREFIX . $this->table . "`";

        $query = $this->db->query($sql);

        $pattern = '/(tinyint|varchar)\((\d+)\)/';

        $types = [
            'int' => 'number',
            'tinyint' => 'number',
            'tinyint(1)' => 'boolean',
            'varchar' => 'string',
            'datetime' => 'string'
        ];

        $validations = [
            'string' => 'max_length',
        ];

        foreach ($query->rows as $row) {
            if (!empty($this->select_fields) && !in_array($row['Field'], $this->select_fields)) {
                continue;
            }

            $validation = null;

            if (isset($types[$row['Type']])) {
                $type = $types[$row['Type']];
            } else {
                $matches = [];

                preg_match($pattern, $row['Type'], $matches);

                if (count($matches) === 3) {
                    $type = $matches[1];

                    if (isset($types[$type])) {
                        $type = $types[$type];
                    } else {
                        throw new \Exception('Invalid type: ' . $row['Type']);
                    }

                    if (isset($validations[$type])) {
                        $validation = $validations[$type] . ':' . $matches[2];
                    }
                } else {
                    throw new \Exception('Invalid type: ' . $row['Type']);
                }
            }

            $column = [
                'key' => $row['Field'],
                'type' => $type,
                'order' => in_array($row['Field'], $this->order_fields),
                'filter' => in_array($row['Field'], $this->filter_fields),
                'edit' => in_array($row['Field'], $this->create_fields),
            ];

            if ($validation !== null) {
                $column['validation'] = $validation;
            }

            $result['columns'][] = $column;
        }

        return $result;
    }

    public function get($filterData = array())
    {
        if (!empty($filterData['offset'])) {
            $safetyOffset = (int)$filterData['offset'];
        } else {
            $safetyOffset = 0;
        }

        if (!empty($filterData['limit'])) {
            $safetyLimit = (int)$filterData['limit'];
        } else {
            $safetyLimit = 0;
        }

        $select_fields = '*';

        if (!empty($this->select_fields)) {
            $select_fields = implode(',', array_map(function ($field) { return '`' . $field . '`'; }, $this->select_fields));
        }

        $sql = "SELECT " . $select_fields . " FROM `" . DB_PREFIX . $this->table . "` " . $this->getFilterSql($filterData);

        if (!empty($filterData['order']['key']) && !empty($filterData['order']['value'])) {
            $key = $filterData['order']['key'];

            if (in_array($key, $this->order_fields) and in_array($filterData['order']['value'], ['asc', 'desc'])) {
                $sql .= " ORDER BY `{$key}` {$filterData['order']['value']} ";
            }
        }

        if ($safetyLimit) {
            $sql .= "LIMIT $safetyOffset, $safetyLimit";
        }

        return $this->db->query($sql)->rows;
    }

    public function getRegistry()
    {
        return $this->registry;
    }

    public function getByIds(array $ids)
    {
        return $this->get(['filter' => [$this->pk => $ids]]);
    }

    public function getById($id)
    {
        $data = $this->getByIds([$id]);

        if (count($data) === 1) {
            return $data[0];
        }

        return null;
    }

    public function getTotal($filterData = array())
    {
        $sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . $this->table . "` " . $this->getFilterSql($filterData);

        return $this->db->query($sql)->row['total'];
    }

    public function create($data)
    {
        $values = array();

        if (array_key_exists($this->pk, $data)) {
            $sql = "SELECT `" . $this->pk . "` FROM `" . DB_PREFIX . $this->table . "` WHERE `" . $this->pk . "` = '" . $this->db->escape($data[$this->pk]) . "'";

            $query = $this->db->query($sql);

            if ($query->num_rows > 0) {
                throw new \Exception(sprintf('Duplicate key="%s" value="%s" !', $this->pk, $data[$this->pk]));
            }
        }

        foreach ($this->create_fields as $field) {
            if (isset($data[$field])) {
                $values[$field] = "`" . $field . "` = '" . $this->db->escape($data[$field]) . "'";
            }
        }

        if ($this->date_added && empty($values[$this->date_added])) {
            $values[$this->date_added] = "`" . $this->date_added . "` = NOW()";
        }

        if (!empty($values)) {
            $sql = "INSERT INTO " . DB_PREFIX . $this->table . " SET " . implode(', ', $values) . " ";

            $this->db->query($sql);

            if (array_key_exists($this->pk, $data)) {
                return $data[$this->pk];
            }

            return $this->db->getLastId();
        }

        throw new \Exception("Invalid Data");
    }

    public function update($ids, $data)
    {
        $values = array();

        foreach ($this->update_fields as $field) {
            if (isset($data[$field])) {
                $values[$field] = "`" . $field . "` = '" . $this->db->escape($data[$field]) . "'";
            }
        }

        if ($this->date_updated && empty($values[$this->date_updated])) {
            $values[$this->date_updated] = "`" . $this->date_updated . "` = NOW()";
        }

        $safetyIds = array_map(function ($id) {
            return "'" . $this->db->escape($id) . "'";
        }, $ids);

        if (!empty($values) && !empty($safetyIds)) {
            $sql = "UPDATE " . DB_PREFIX . $this->table . " SET " . implode(', ', $values) . " WHERE `" . $this->pk . "` IN (" . implode(",", $safetyIds) . ")";

            $this->db->query($sql);

            return $this->db->countAffected();
        }

        throw new \Exception("Invalid Data");
    }

    public function delete(array $ids)
    {
        $sql = "DELETE FROM " . DB_PREFIX . $this->table . " WHERE `" . $this->pk . "` IN (" . implode(',', array_map(function($id) {
            return "'" . $this->db->escape($id) . "'";
            }, $ids)
        ) . ")";

        $this->db->query($sql);
    }

    public function clone(array $ids)
    {
        $insertIds = [];

        foreach ($ids as $id) {
            $data = $this->getById($id);

            if (!empty($data)) {
                unset($data[$this->pk]);

                $insertedId = $this->create($data);

                if ($insertedId) {
                    $insertIds[] = $insertedId;
                }
            }
        }

        return $insertIds;
    }

    protected function getFilterSql($filterData = array())
    {
        $sql = '';

        if (!empty($filterData['filter']) && is_array($filterData['filter'])) {
            $values = [];

            foreach ($filterData['filter'] as $key => $rawValue) {
                if (is_array($rawValue)) {
                    if (empty($rawValue)) {
                        continue;
                    }

                    $safetyValue = implode(',', array_map(function($val) {
                        return "'" . $this->db->escape($val) . "'";
                    }, $rawValue));
                } elseif (is_string($rawValue)) {
                    $safetyValue = $this->db->escape($rawValue);
                } elseif (is_numeric($rawValue)) {
                    $safetyValue = $rawValue;
                } elseif (is_bool($rawValue)) {
                    $safetyValue = (int)$rawValue;
                } else {
                    throw new \Exception("Invalid Value");
                }

                if (in_array($key, $this->filter_fields) || $key === $this->pk) {
                    if ($key === $this->pk) {
                        $expression = " `%s` = '%s' ";
                    } else {
                        $expression = " `%s` LIKE '%%%s%%' ";
                    }

                    if (is_array($rawValue)) {
                        $expression = " `%s` IN (%s) ";
                    }

                    $values[] = sprintf($expression, $this->db->escape($key), $safetyValue);
                }
            }

            if (!empty($values)) {
                $sql .= " WHERE " . implode(" AND ", $values);
            }
        }

        return $sql;
    }

    public function dropTable()
    {
        $sql = "DROP TABLE " . DB_PREFIX . $this->table;

        $this->db->query($sql);
    }

    abstract public function createTable();
}