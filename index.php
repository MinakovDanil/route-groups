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


    // public function removeLeadsPay()
    // {

    //     // Соединение с БД
    //     $db = Db::getConnection();

    //     $sql = "DELETE FROM leads WHERE carrier_pay < 100";
    //     $result = $db->query($sql);
    // }


    public function createGroup()
    {

        // Соединение с БД
        $db = Db::getConnection();

        // Основные штаты (48)
        $fifty_states = ['AL', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DE', 'FL', 'GA', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD', 'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY'];


        $zip_codes = $this->getZipRadius();


        // id маршрутов группы
        $delivery_ids = [];

        // Все delivery зипы 
        $delivery = [];


        // Перебор зипов который нашли в радиусе 30 миль
        foreach ($zip_codes as $zip_item) {

            // Получения delivery_zip в таблице лидов 
            $sql = "SELECT id, delivery_zip, delivery_state FROM leads WHERE origin_zip = '$zip_item' AND delivery_zip != '$zip_item'";
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

        $delivery_count = count($delivery);


        // if ($delivery_count >= 300) {

        // Подсчет всех значения массива
        $delivery_count_values = array_count_values($delivery);




        // Отсутствующие штаты
        $missing_states = [];


        // Перебор основных штатов
        foreach ($fifty_states as $state_item) {
            // Если хотя бы одного основного штата нет в массиве $delivery_count_values
            if (!array_key_exists($state_item, $delivery_count_values)) {

                // Заносим все отсутствующие штаты в массив
                array_push($missing_states, $state_item);
            }
        }

        echo "<hr>Группа: $this->city ";
        echo "<br>Всего маршрутов: " . count($delivery_ids);
        echo "<br>Уникальных маршрутов: " . $delivery_count;

        echo "<br>Отсутствующие штаты: " . implode(", ", $missing_states);


        if (min($delivery_count_values) < 4) {
            echo '<br>';
            $minimal_delivery_state = min($delivery_count_values);
            echo "Минимальное количество delivery в штате: $minimal_delivery_state";
        }

        echo '<pre>';
        print_r($delivery_count_values);
        echo '</pre><br>';
        // }
    }
}


// $group = new Group('New York', 'New York', 11213, 40.66, -73.94);
// $group = new Group('Los Angeles', 'California', 90034, 34.02, -118.41);
// $group = new Group('Chicago', 'Illinois', 60608, 41.84, -87.68);
// $group = new Group('Houston', 'Texas', 77009, 29.78, -95.39);
// $group = new Group('Philadelphia', 'Pennsylvania', 19140, 40.01, -75.13);
// $group = new Group('Phoenix', 'Arizona', 85021, 33.57, -112.09);
// $group = new Group('San Antonio', 'Texas', 78201, 29.47, -98.53);
// $group = new Group('San Diego', 'California', 92123, 32.82, -117.14);
// $group = new Group('Dallas', 'Texas', 75201, 32.78, -96.80);
$group = new Group('San Jose', 'California', 95121, 37.30, -121.82);
// $group = new Group('Boston', 'Massachusetts', 02127, 42.33, -71.02); // 0
$group->createGroup();



// $cities = new BiggestCities;
// $cities_array = $cities->getCities();
// $cities_count = count($cities_array);

// for ($i = 0; $i <= 30; $i++) {

//     $city = $cities_array[$i]['city'];
//     $state = $cities_array[$i]['state'];
//     $zip = $cities_array[$i]['zip'];
//     $lat = $cities_array[$i]['lat'];
//     $lon = $cities_array[$i]['lon'];

//     $grop = new Group($city, $state, $zip, $lat, $lon);
//     $grop->createGroup();
// }
