<?php
require_once('Db.php');

class BiggestCities
{
    public function getCities()
    {
        // Соединение с БД
        $db = Db::getConnection();

        $sql = 'SELECT * FROM biggest_cities';
        $result = $db->query($sql);


        $cities = [];

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                array_push($cities, $row);
            }
        }

        return $cities;
    }
}
