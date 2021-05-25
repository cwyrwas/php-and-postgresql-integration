<?php
namespace jspreadsheet;

use bossanova\Database\Database;

class Jspreadsheetdb
{
    /** Tablename **/
    public $table = null;

    /** Database instance **/
    public $database = null;

    /** Primary key **/
    public $primaryKey = null;

    /**
     * Create the jexcel to postgresql instance
     * @param Database $instance
     * @param string $table
     */
    public function __construct(Database $instance = null, $table)
    {
        if (isset($instance)) {
            $this->database = $instance;
        } else {
            $this->database = Database::getInstance();
        }

        $this->table = $table;
    }

    /**
     * Set the primary key of table
     * @param string $primaryKey
     */
    public function setPrimaryKey($primaryKey)
    {
        $this->primaryKey = $primaryKey;
    }

    /**
     * Save the information back to the table
     * @param array $post
     * @return string[]
     */
    public function save($post)
    {
        // Parse the posted json
        $request = json_decode($post['data'], true);

        // Process the setValue request
        if (isset($request['setValue']) && $request['setValue']) {
            // Process all records
            foreach ($request['setValue'] as $v) {
                // Verify if the record exists in the table
                $result = $this->database->table($this->table)
                    ->argument(1, $this->primaryKey, $v['id'])
                    ->execute();

                // Exists, so update the table with the correct columns
                if ($this->database->fetch_assoc($result)) {
                    $this->database->column($v['data'], true)
                        ->update()
                        ->execute();
                } else {
                    // No, create a new record
                    $this->database->column($v['data'], true)
                        ->insert()
                        ->execute();
                }
            }

            return [
                'success' => 1,
                'message' => 'Updated',
            ];
        }
    }

    public function create($options)
    {
        // Basic configurations, can be replaced
        $config = array_merge([
            'columns' => [],
            'data' => [],
            'persistance' => true,
            'defaultColWidth' => '100px',
            'tableOverflow' => true,
            'tableWidth' => '1200px',
            'lazyloading' => true,
        ], $options);

        // Get all column information
        if ($rows = $this->database->getColumns($this->table)) {
            // Get the data in the table
            $result = $this->database->table($this->table)
                ->order($this->primaryKey)
                ->execute();

            // Load all records
            while ($row = $this->database->fetch_assoc($result)) {
                $config['data'][] = $row;
            }

            foreach ($rows as $v) {
                // Crate the columns
                $column = [
                    'type' => $this->getType($v['data_type']),
                    'name' => $v['column_name'],
                    'title' => $v['column_name'],
                ];

                // This columns is the primary key
                if ($this->primaryKey && $this->primaryKey == $v['column_name']) {
                    $column['primaryKey'] = true;
                    $column['readOnly'] = true;
                    $column['type'] = 'number';
                }

                $config['columns'][] = $column;
            }

            // Stringfy the configuration
            $config = json_encode($config);

            // Randon ID just in case
            if (! isset($options['id'])) {
                $options['id'] = 'j' . rand(1000,9000);
            }

            // Create the Jexcel configuration
            return "<div id='{$options['id']}'></div><script>jspreadsheet(document.getElementById('{$options['id']}'), {$config});</script>";
        } else {
            // Table not found
            return 'Not found';
        }
    }

    /**
     * Treat more cases
     * @param string $type
     * @return string
     */
    public function getType($type) {
        if (strpos($type, 'int') !== false || strpos($type, 'numeric') !== false) {
            return 'number';
        } else if (strpos($type, 'timestamp')  !== false || strpos($type, 'calendar')  !== false) {
            return 'calendar';
        } else {
            return 'text';
        }
    }
}
