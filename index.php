<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once('Db.php');
require_once('BiggestCities.php');


class Group
{

    public function __construct($city, $state, $zip, $latitude, $longitude, $radius = 30)
    {
        $this->city = $city;
        $this->state = $state;
        $this->zip = $zip;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->radius = $radius;
    }


    // Функция для расчета расстояния между двумя точками на поверхности Земли
    public function distance($lat1, $lon1, $lat2, $lon2, $unit)
    {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        if ($unit == "K") {
            return ($miles * 1.609344);
        } else if ($unit == "N") {
            return ($miles * 0.8684);
        } else {
            return $miles;
        }
    }


    // Получение зипкодов в нужной радиусе
    public function getZipRadius()
    {

        // Соединение с БД
        $db = Db::getConnection();

        // Вычисляем ограничивающий радиус области поиска
        $latRange = $this->radius / 69.172;
        $lonRange = abs($this->radius / (cos(deg2rad($this->latitude)) * 69.172));
        $minLat = $this->latitude - $latRange;
        $maxLat = $this->latitude + $latRange;
        $minLon = $this->longitude - $lonRange;
        $maxLon = $this->longitude + $lonRange;

        // Массив зипкодов
        $zip_codes = [];

        // Находим все почтовые индексы в радиусе
        $sql = "SELECT zip, city, lat, lon FROM cities WHERE lat BETWEEN $minLat AND $maxLat AND lon BETWEEN $minLon AND $maxLon";
        $result = $db->query($sql);
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Рассчитываем расстояние между нашим почтовым индексом и данным почтовым индексом
                $distance = $this->distance($this->latitude, $this->longitude, $row['lat'], $row['lon'], "M");
                if ($distance <= $this->radius) {
                    // Этот почтовый индекс находится в нужном радиусе, заносим его в массив
                    array_push($zip_codes, $row['zip']);
                }
            }
        }

        return $zip_codes;
    }


    public function removeLeadsPay()
    {

        // Соединение с БД
        $db = Db::getConnection();

        $sql = "DELETE FROM leads_2021 WHERE carrier_pay < 100";
        $result = $db->query($sql);
    }



    public function createGroup()
    {

        // Соединение с БД
        $db = Db::getConnection();

        // Основные штаты (48)
        $fifty_states = ['AL', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD', 'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY'];


        $zip_codes = $this->getZipRadius();

        // id маршрутов группы (Все маршруты)
        $delivery_ids = [];

        // Все уникальные delivery зипы 
        $delivery = [];


        // Перебор зипов который нашли в радиусе 30 миль
        foreach ($zip_codes as $zip_item) {

            // Получения delivery_zip в таблице лидов 
            $sql = "SELECT id, delivery_zip, delivery_state FROM leads_common WHERE origin_zip = '$zip_item' AND delivery_zip != '$zip_item'";
            $result = $db->query($sql);

            if ($result->num_rows > 0) {


                // Delivery данного зипкода
                $current_delivery = [];


                while ($row = $result->fetch_assoc()) {

                    // Если delivery_state есть в массиве $fifty_states
                    if (in_array($row['delivery_state'], $fifty_states)) {

                        // Добавляем id маршрута в массив $delivery_ids
                        array_push($delivery_ids, $row['id']);

                        // Добавляем зипкод и штат в массив (одинаковые маршруты будут перезаписываться)
                        $current_delivery[$row['delivery_zip']] = $row['delivery_state'];
                    }
                }


                // Добавляем штаты данного зипкода в общий массив
                $delivery = array_merge($delivery, $current_delivery);
            }
        }



        // Количество уникальных delivery
        $delivery_count = count($delivery);


        if ($delivery_count > 0) {

            // Подсчет всех delivery зипов в каждом штате
            $delivery_count_values = array_count_values($delivery);

            // Количество delivery зипов в штате в json формате
            $json_delivery_states = json_encode($delivery_count_values);

            // Отсутствующие штаты
            $missing_states = [];


            // Минимальное количество delivery в штатах
            $minimum_number_states = min($delivery_count_values);


            // Перебор основных штатов
            foreach ($fifty_states as $state_item) {
                // Если хотя бы одного основного штата нет в массиве $delivery_count_values
                if (!array_key_exists($state_item, $delivery_count_values)) {

                    // Заносим данный штат в массив отсутствующих
                    array_push($missing_states, $state_item);
                }
            }


            $sql = "INSERT INTO zip_groups (city, state, lat, lon, zip, count_delivery_zip, minimum_number_states, count_states) VALUES ('$this->city', '$this->state', $this->latitude, $this->longitude, '$this->zip', $delivery_count, $minimum_number_states, '$json_delivery_states')";
            $result = $db->query($sql);

            // id группы
            $current_group_id = $db->insert_id;

            // Перебираем id всех маршрутов данной группы
            foreach ($delivery_ids as $id) {
                // Добавляем маршруту id группы
                $sql = "UPDATE leads_common SET group_id = $current_group_id WHERE id = $id";
                $db->query($sql);
            }



            echo "<hr>Группа: $this->city";
            echo "<br>Всего маршрутов: " . count($delivery_ids);
            echo "<br>Уникальных маршрутов: " . $delivery_count;

            echo "<br>Отсутствующие штаты: " . implode(", ", $missing_states);

            echo '<pre>';
            print_r($delivery_count_values);
            echo '</pre>';
            echo 'В json формате: ';
            echo $json_delivery_states;
        } else {


            echo "<br><br><br>Группа: $this->city<br>Всего маршрутов:0<br><br><br>";

            $sql = "INSERT INTO zip_groups (city, state, lat, lon, zip, count_delivery_zip, minimum_number_states, count_states) VALUES ('$this->city', '$this->state', $this->latitude, $this->longitude, '$this->zip', 0, 0, '')";
            $result = $db->query($sql);
        }
    }
}


// $group = new Group('New York', 'New York', 11213, 40.66, -73.94);
// $group->createGroup();
// $group->checkBiggestCities();



$cities = new BiggestCities;
$cities_array = $cities->getCities();
$cities_count = count($cities_array);

for ($i = 300; $i < $cities_count; $i++) {

    $city = $cities_array[$i]['city'];
    $state = $cities_array[$i]['state'];
    $zip = $cities_array[$i]['zip'];
    $lat = $cities_array[$i]['lat'];
    $lon = $cities_array[$i]['lon'];

    $grop = new Group($city, $state, $zip, $lat, $lon);
    $grop->createGroup();
}
