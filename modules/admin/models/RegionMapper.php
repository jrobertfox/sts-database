<?php
class Admin_Model_RegionMapper
{
    const TABLE_NAME = 'Admin_Model_DbTable_Region';
    protected $dbTable;

    public function setDbTable($dbTable)
    {
        if (is_string($dbTable)) {
            $dbTable = new $dbTable();
        }
        if (! $dbTable instanceof Zend_Db_Table_Abstract) {
            throw new Exception('Invalid table data gateway provided');
        }
        $this->dbTable = $dbTable;
        return $this;
    }

    public function getDbTable()
    {
        if (null === $this->dbTable) {
            $this->setDbTable(self::TABLE_NAME);
        }
        return $this->dbTable;
    }

    public function save(Admin_Model_Region $region)
    {}

    public function find($id)
    {
        $row = $this->getDbTable()
            ->fetchRow($this->getDbTable()->select()
            ->where('id = ?', $id));
        return $this->buildDomainObject($row);
    }

    public function fetchAll()
    {
        $resultSet = $this->getDbTable()
            ->fetchAll();
        $entries = array();
        foreach ($resultSet as $row) {
            $entries[] = $this->buildDomainObject($row);
        }
        return $entries;
    }

    private function buildDomainObject($row)
    {
        $object = new Admin_Model_Region();
        $parameters = array();
        foreach ($row as $key => $value) {
            $func = create_function('$c', 'return strtoupper($c[1]);');
            $parameter = preg_replace_callback('/_([a-z])/', $func, $key);
            $parameters[$parameter] = $value;
        }
        return $object->setOptions($parameters);
    }
}

