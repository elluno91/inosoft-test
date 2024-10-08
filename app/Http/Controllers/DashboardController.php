<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use function Symfony\Component\String\s;

class DashboardController extends Controller
{
    public $headQuarterLatitude = "-7.9826";
    public $headQuarterLongitude = "112.6308";
    public $countSales = 10;
    public $totalMaxVisitStore = 30;
    public $date = "2024-10-01";

    public function index() {
        return view('dashboard');
    }

    public function store(Request $request) {
        set_time_limit(300);

        if($request->hasFile('file')) {
            $file = $request->file('file');
            $file_content = file_get_contents($file->getRealPath());

            //explode by line
            $data_no_linebreak = explode("\n", $file_content);
            //remove first array (because contain description)
            $data_no_linebreak = array_slice($data_no_linebreak, 1, -1);

            //list sales
            $sales = array();
            //create dummy sales
            for ($i = 0; $i < $this->countSales; $i++) {
                $sales[] = array(
                    'code' => str_pad($i + 1, 5, "0", STR_PAD_LEFT),
                    'name' => "Sales " . ($i + 1),
                    'schedule' => array(),
                );
            }

            //list store
            $stores = array();
            $invalid_stores = array();
            foreach ($data_no_linebreak as $index => $value) {

                $explode = explode(",", $value);
                //Get total monday in a month
                $total_monday = $this->countMondayInMonth($this->date);

                switch (trim(strtolower($explode[6]))) {
                    case "weekly":
                        $remaining_visit = $total_monday;
                        break;
                    case "biweekly":
                        $remaining_visit = 2;
                        break;
                    case "monthly":
                        $remaining_visit = 1;
                        break;
                    default:
                        $remaining_visit = 0;
                        break;
                }

                if ($explode[2] != 0 || $explode[3] != 0) {
                    $stores[] = array(
                        'name' => trim($explode[0]),
                        'code' => trim($explode[1]),
                        'longitude' => $explode[2],
                        'latitude' => $explode[3],
                        'address' => trim($explode[4]),
                        'postal_code' => trim($explode[5]),
                        'interval' => strtolower(trim($explode[6])),
                        'visit_remaining' => $remaining_visit,
                        'visited_date' => array(),
                        'km_distance_from_headquarter' => $this->distance($this->headQuarterLatitude, $this->headQuarterLongitude, $explode[3], $explode[2]),
                    );
                } else {
                    $invalid_stores[] = array(
                        'name' => trim($explode[0]),
                        'code' => trim($explode[1]),
                        'longitude' => $explode[2],
                        'latitude' => $explode[3],
                        'address' => trim($explode[4]),
                        'postal_code' => trim($explode[5]),
                        'interval' => strtolower(trim($explode[6])),
                        'visit_remaining' => $remaining_visit,
                        'visited_date' => array(),
                        'km_distance_from_headquarter' => $this->distance($this->headQuarterLatitude, $this->headQuarterLongitude, $explode[3], $explode[2]),
                    );
                }
            }

            //sort store by near to far from headquarters
            $keys = array_column($stores, 'km_distance_from_headquarter');
            array_multisort($keys, SORT_ASC, $stores);

            $total_date_in_month = date("t", strtotime($this->date));
            for($i=0;$i<count($sales);$i++) {
                for($j=1;$j<=$total_date_in_month;$j++) {
                    $month = date("m", strtotime($this->date));
                    $year = date("Y", strtotime($this->date));
                    $iso_numeric_date = date("N", strtotime($year."-".$month."-".$j));

                    if($iso_numeric_date != 7) {
                        $sales[$i]['schedule'][] = array(
                            'date' => $year . "-" . $month . "-" . str_pad($j,2,"0",STR_PAD_LEFT),
                            'remaining_visit_store' => $this->totalMaxVisitStore,
                            'store' => array(),
                        );
                    }
                }
            }

            $is_all_visited = false;
            $ctr = 0;
            $cur_index_store = 0;
            while(!$is_all_visited) {
                for($i=1;$i<=$total_date_in_month;$i++) {
                    $month = date("m", strtotime($this->date));
                    $year = date("Y", strtotime($this->date));
                    $date = $year . "-" . $month . "-" . str_pad($i,2,"0",STR_PAD_LEFT);

                    foreach($sales as $index => $value) {
                        $sales_schedules = $value['schedule'];
                        $is_sales_visited_a_store = false;

                        foreach($sales_schedules as $index2 => $sales_schedule) {

                            //check if sales has remaining visit store for spesific date
                            if($sales_schedule['date'] == $date && $sales_schedule['remaining_visit_store'] != 0) {

                                if( count($sales_schedule['store']) > 0) {

                                    $cur_index_store = 0;

                                    $last_visit_store = $sales_schedule['store'][count($sales_schedule['store']) - 1];

                                    $diff_distance = null;
                                    $nearest_store = null;
                                    $last_distance_with_last_store = 0;

                                    $exclude_stores = array();


                                    foreach ($stores as $index3 => $store) {

                                        if ($store['visit_remaining'] > 0) {

                                            $can_visit_store = true;

                                            if ($last_distance_with_last_store == 0) {
                                                if($last_visit_store['code'] != $store['code']) { // cek apakah store terakhir yang dikunjungi sama atau tidak dengan store yang akan di looping
                                                    $diff_distance = $this->distance($store['latitude'], $store['longitude'], $last_visit_store['latitude'], $last_visit_store['longitude']);

                                                } else {
                                                    $can_visit_store = false;
                                                }

                                            } else {

                                                if ($nearest_store['code'] != $store['code']) { // cek apakah store terakhir yang dikunjungi sama atau tidak dengan store yang akan di looping
                                                    $diff_distance = $this->distance($store['latitude'], $store['longitude'], $nearest_store['latitude'], $nearest_store['longitude']);
                                                } else {
                                                    $can_visit_store = false;
                                                }
                                            }

                                            if ($can_visit_store) {
                                                //check if sales already visit same store in same date
                                                foreach ($sales_schedule['store'] as $index4 => $sales_store) {
                                                    if ($sales_store['code'] == $store['code']) {
                                                        $can_visit_store = false;
                                                        $err_message = "$date - Sudah divisit oleh sales yang sama";
                                                        break;
                                                    }
                                                }
                                            }


                                            if ($can_visit_store) {
                                                if(count($store['visited_date'])>0) {
                                                    $store_visited_dates = $store['visited_date'];
                                                    //check if store already visited in same date or not

                                                    foreach ($store_visited_dates as $index4 => $store_visited_date) {

                                                        if ($store_visited_date == $date) {

                                                            $can_visit_store = false;

                                                            $err_message = "$date - Sudah pernah divisit di tanggal yang sama";
                                                            break;
                                                        }
                                                    }
                                                 }
                                            }


                                            if($can_visit_store) {
                                                if(count($store['visited_date'])>0) {

                                                    $visited_dates = $store['visited_date'];
                                                    $diff_day_last_visit_second = 0;
                                                    if(strtotime($date) > strtotime($visited_dates[count($visited_dates) - 1])) {
                                                        $diff_day_last_visit_second =  strtotime($date) - strtotime($visited_dates[count($visited_dates) - 1]);
                                                    } else {
                                                        $diff_day_last_visit_second = strtotime($visited_dates[count($visited_dates) - 1]) - strtotime($date);
                                                    }
                                                    $diff_day_last_visit = round($diff_day_last_visit_second / (60 * 60 * 24));

                                                    //cek apakah tanggal sekarang diperbolehkan untuk kunjungan toko kembali atau tidak

                                                    if ($store['interval'] == "biweekly") {
                                                        //jika biweekly maka interval kunjungan ke toko kembali adalah 14 hari
                                                        if ($diff_day_last_visit < 14) {
                                                            $can_visit_store = false;
                                                        }
                                                    } else if ($store['interval'] == "weekly") {
                                                        //jika weekly maka interval kunjungan ke toko kembali adalah 14 hari
                                                        if ($diff_day_last_visit < 7) {
                                                            $can_visit_store = false;
                                                        }
                                                    }

                                                }
                                            }


                                            if ($can_visit_store) {
                                                //cek apakah toko yang sedang diperiksa belum pernah dilakukan kunjungan dan jumlah kunjugan yang terisa masih lebih dari 0
                                                $store_visited_dates = $store['visited_date'];
                                                if (count($store_visited_dates) == 0 && $store['visit_remaining'] != 0) {
                                                    if ($store['interval'] == "biweekly") {

                                                        $temp_date = $date;
                                                        for ($j = 1; $j < $store['visit_remaining']; $j++) {

                                                            $newDate = date('Y-m-d', strtotime($temp_date . " +14 days"));
                                                            if (date("Y-m", strtotime($newDate)) != $year . "-" . $month) {
                                                                $can_visit_store = false;
                                                                $err_message = "$date - Sudah beda bulan";

                                                                break;
                                                            } else {
                                                                $temp_date = date('Y-m-d', strtotime($temp_date . " +14 days"));
                                                            }
                                                        }

                                                    } else if ($store['interval'] == "weekly") {

                                                        $temp_date = $date;
                                                        for ($j = 1; $j <= $store['visit_remaining']; $j++) {
                                                            $newDate = date('Y-m-d', strtotime($temp_date . " +7 days"));
                                                            if (date("Y-m", strtotime($newDate)) != $year . "-" . $month) {
                                                                $can_visit_store = false;
                                                                $err_message = "$date - Sudah beda bulan";

                                                                break;
                                                            } else {
                                                                $temp_date = date('Y-m-d', strtotime($temp_date . " +7 days"));
                                                            }
                                                        }

                                                    }
                                                }
                                            }


                                            if ($can_visit_store && ($last_distance_with_last_store > $diff_distance || $last_distance_with_last_store == 0)) {

                                                $last_distance_with_last_store = $diff_distance;
                                                $nearest_store = $store;
                                            }
                                        }
                                    }

                                    if($nearest_store == null) {
                                        $can_visit_store = false;
                                    }


                                    if ($can_visit_store) {
                                        //if can visit, log to sales and store
                                        $sales_schedule['remaining_visit_store'] -= 1;
                                        $sales_schedule['store'][] = $nearest_store;
                                        $sales_schedules[$index2] = $sales_schedule;
                                        $sales[$index]['schedule'] = $sales_schedules;

                                        foreach ($stores as $index3 => $store) {
                                            if ($nearest_store['code'] == $store['code']) {
                                                $store['visit_remaining'] -= 1;
                                                $store['visited_date'][] = $date;
                                                $store['distance'] = $last_distance_with_last_store;
                                                sort($store['visited_date'], SORT_ASC);
                                                $stores[$index3] = $store;
                                                break;
                                            }
                                        }
                                        break;
                                    }

                                } else {
                                    $store = $stores[$cur_index_store];

                                    $sales_schedule['remaining_visit_store'] -= 1;
                                    $sales_schedule['store'][] = $store;
                                    $sales_schedules[$index2] = $sales_schedule;
                                    $sales[$index]['schedule'] = $sales_schedules;

                                    $store['visit_remaining'] -= 1;
                                    $store['visited_date'][] = $date;
                                    $stores[$cur_index_store] = $store;

                                    $cur_index_store++;
                                    break;
                                }
                            }

                            if($is_sales_visited_a_store) {

                                break;
                            }
                        }
                    }

                }

                $is_all_visited = true;
                foreach($stores as $index => $store) {
                    if($store['visit_remaining'] > 0) {
                        $is_all_visited = false;
                        break;
                    }
                }
                $ctr++;
            }

            echo "is all visited : ".$is_all_visited;
            echo "<pre>";

            $sales_schedules = $sales[0]['schedule'][12]['store'];
            foreach($sales_schedules as $index => $sales_schedule) {
                echo $sales_schedule['latitude'].",".$sales_schedule['longitude'].",red,marker,\"".$sales_schedule['name']."\"";
                echo "<br />";
            }

            die();


            echo "<table cellpadding='10' cellspacing='10' border='1'>";
            echo "<tr>";
            echo "<td>Sales</td>";
            for($i=1;$i<=$total_date_in_month;$i++) {
                $month = date("m", strtotime($this->date));
                $year = date("Y", strtotime($this->date));
                $date = str_pad($i,2,"0",STR_PAD_LEFT);
                echo "<td>".$date."-".$month."-".$year."</td>";
            }
            echo "</tr>";
            for($i=0;$i<count($sales);$i++) {
                echo "<tr>";
                echo "<td>".$sales[$i]['name']."</td>";
                for($j=1;$j<=$total_date_in_month;$j++) {
                    $current_date = str_pad($j,2,"0",STR_PAD_LEFT);
                    $sales_schedules = $sales[$i]['schedule'];
                    $is_empty = true;
                    foreach($sales_schedules as $index => $sales_schedule) {
                        if(date("d",strtotime($sales_schedule['date'])) == $current_date) {
                            echo "<td>";
                            echo "<ol>";
                            $stores = $sales_schedule['store'];
                            foreach($stores as $index => $store) {
                                echo "<li>".$store['name']." - ".$store['interval']."</li>";
                            }

                            echo "</ol>";
                            echo "</td>";
                            $is_empty = false;

                            break;
                        }
                    }
                    if($is_empty) {
                        echo "<td>-</td>";
                    }
                }
                echo "</tr>";
            }
            echo "</table>";
        }
    }


    public function store2(Request $request) {
        if($request->hasFile('file')) {
            $file = $request->file('file');
            $file_content = file_get_contents($file->getRealPath());

            //explode by line
            $data_no_linebreak = explode("\n", $file_content);
            //remove first array (because contain description)
            $data_no_linebreak = array_slice($data_no_linebreak, 1, -1);

            //list sales
            $sales = array();
            //create dummy sales
            for($i=0; $i<$this->countSales;$i++) {
                $sales[] = array(
                    'code' => str_pad($i+1, 5, "0", STR_PAD_LEFT),
                    'name' => "Sales ".($i+1),
                    'schedule' => array(),
                );
            }

            //list store
            $stores = array();
            $invalid_stores = array();
            foreach($data_no_linebreak as $index => $value) {

                $explode = explode(",", $value);
                //Get total monday in a month
                $total_monday = $this->countMondayInMonth($this->date);

                switch (trim(strtolower($explode[6]))) {
                    case "weekly":
                        $remaining_visit = $total_monday;
                        break;
                    case "biweekly":
                        $remaining_visit = 2;
                        break;
                    case "monthly":
                        $remaining_visit = 1;
                        break;
                    default:
                        $remaining_visit = 0;
                        break;
                }

                if($explode[2] != 0 || $explode[3] != 0) {
                    $stores[] = array(
                        'name' => trim($explode[0]),
                        'code' => trim($explode[1]),
                        'longitude' => $explode[2],
                        'latitude' => $explode[3],
                        'address' => trim($explode[4]),
                        'postal_code' => trim($explode[5]),
                        'interval' => strtolower(trim($explode[6])),
                        'visit_remaining' => $remaining_visit,
                        'visited_date' => array(),
                        'km_distance_from_headquarter' => $this->distance($this->headQuarterLatitude, $this->headQuarterLongitude, $explode[3], $explode[2]),
                    );
                } else {
                    $invalid_stores[] = array(
                        'name' => trim($explode[0]),
                        'code' => trim($explode[1]),
                        'longitude' => $explode[2],
                        'latitude' => $explode[3],
                        'address' => trim($explode[4]),
                        'postal_code' => trim($explode[5]),
                        'interval' => strtolower(trim($explode[6])),
                        'visit_remaining' => $remaining_visit,
                        'visited_date' => array(),
                        'km_distance_from_headquarter' => $this->distance($this->headQuarterLatitude, $this->headQuarterLongitude, $explode[3], $explode[2]),
                    );
                }
            }

            //sort store by near to far from headquarters
            $keys = array_column($stores, 'km_distance_from_headquarter');
            array_multisort($keys, SORT_ASC, $stores);

            //grouping store by interval visit
            $store_weekly = array();
            $store_biweekly = array();
            $store_monthly = array();

            foreach($stores as $index => $value) {
                if(strtolower($value['interval']) == 'weekly') {
                    $store_weekly[] = $value;
                } elseif(strtolower($value['interval']) == 'biweekly') {
                    $store_biweekly[] = $value;
                } elseif(strtolower($value['interval']) == 'monthly') {
                    $store_monthly[] = $value;
                }
            }

            $total_date_in_month = date("t", strtotime($this->date));
            for($i=0;$i<count($sales);$i++) {
                for($j=1;$j<=$total_date_in_month;$j++) {
                    $month = date("m", strtotime($this->date));
                    $year = date("Y", strtotime($this->date));
                    $iso_numeric_date = date("N", strtotime($year."-".$month."-".$j));

                    if($iso_numeric_date != 7) {
                        $sales[$i]['schedule'][] = array(
                            'date' => $year . "-" . $month . "-" . str_pad($j,2,"0",STR_PAD_LEFT),
                            'remaining_visit_store' => $this->totalMaxVisitStore,
                            'store' => array(),
                        );
                    }
                }
            }


            echo "<pre>";
            echo "Weekly : ".count($store_weekly)." Biweekly :".count($store_biweekly)." Monthly :".count($store_monthly);
            echo "<br/>";
            echo "Total Monday : ".$this->countMondayInMonth($this->date);
            echo "<br/>";

            echo "<pre>";
            $is_all_visited = false;
            while(!$is_all_visited) {
                for($i=1;$i<=$total_date_in_month;$i++) {
                    $month = date("m", strtotime($this->date));
                    $year = date("Y", strtotime($this->date));
                    $date = $year . "-" . $month . "-" . str_pad($i,2,"0",STR_PAD_LEFT);

                    foreach($sales as $index => $value) {
                        $sales_schedules = $value['schedule'];
                        $is_sales_visited_a_store = false;

                        foreach($sales_schedules as $index2 => $sales_schedule) {

                            //check if sales has remaining visit store for spesific date
                            if($sales_schedule['date'] == $date && $sales_schedule['remaining_visit_store'] != 0) {

                                //looping every data store
                                foreach($stores as $index3 => $store) {
                                    $can_visit_store = true;
                                    $err_message = "";


                                    //check if store has quota for sales visit
                                    if ($store['visit_remaining'] <= 0) {
                                        $can_visit_store = false;
                                    }

                                    if($can_visit_store) {
                                        //check if sales already visit same store in same date
                                        foreach ($sales_schedule['store'] as $index4 => $sales_store) {
                                            if ($sales_store['code'] == $store['code']) {
                                                $can_visit_store = false;
                                                $err_message = "$date - Sudah divisit oleh sales yang sama";
                                                break;
                                            }
                                        }
                                    }

                                    if($can_visit_store) {
                                        $store_visited_dates = $store['visited_date'];
                                        //check if store already visited in same date or not

                                        foreach ($store_visited_dates as $index4 => $value) {

                                            if ($value == $date) {
                                                $can_visit_store = false;

                                                $err_message = "$date - Sudah divisit di hari yang sama";
                                                break;
                                            }
                                        }
                                    }

                                    if($can_visit_store) {
                                        //check if date is suitable for another visit (more than 1) in one month

                                        $store_visited_dates = $store['visited_date'];
                                        if(count($store_visited_dates) == 0 && $store['visit_remaining'] != 0) {
                                            if ($store['interval'] == "biweekly") {

                                                $temp_date = $date;
                                                for($j = 1; $j < $store['visit_remaining'];$j++) {

                                                    $newDate = date('Y-m-d', strtotime($temp_date . " +14 days"));
                                                    if (date("Y-m",strtotime($newDate)) != $year . "-" . $month) {
                                                        $can_visit_store = false;
                                                        $err_message = "$date - Sudah beda bulan";

                                                        break;
                                                    } else {
                                                        $temp_date = date('Y-m-d', strtotime($temp_date . " +14 days"));
                                                    }
                                                }

                                            } else if ($store['interval'] == "weekly") {

                                                $temp_date = $date;
                                                for($j = 1; $j<= $store['visit_remaining'];$j++) {
                                                    $newDate = date('Y-m-d', strtotime($temp_date . " +7 days"));
                                                    if (date("Y-m",strtotime($newDate)) != $year . "-" . $month) {
                                                        $can_visit_store = false;
                                                        $err_message = "$date - Sudah beda bulan";


                                                        break;
                                                    } else {
                                                        $temp_date = date('Y-m-d', strtotime($temp_date . " +7 days"));
                                                    }
                                                }

                                            }
                                        }
                                    }

                                    if($can_visit_store) {
                                        //check interval visit store monthly / biweekly / weekly

                                        $store_visited_dates = $store['visited_date'];
                                        if(count($store_visited_dates) != 0) {
                                            $diff_day_last_visit_second = strtotime($date) - strtotime($store_visited_dates[count($store_visited_dates) -1]);
                                            $diff_day_last_visit = round($diff_day_last_visit_second / (60 * 60 * 24));


                                            if($store['interval'] == "biweekly") {
                                                if($diff_day_last_visit < 14 || $diff_day_last_visit > 21) {
                                                    $can_visit_store = false;

                                                    $err_message = "$date - Belum 14 hari";
                                                }
                                            } else  if($store['interval'] == "weekly") {
                                                if($diff_day_last_visit < 7 || $diff_day_last_visit > 14) {
                                                    $can_visit_store = false;

                                                    $err_message = "$date - Belum 7 hari";
                                                }
                                            }
                                        }
                                    }

                                    if($can_visit_store) {
                                        //if can visit, log to sales and store
                                        $sales_schedule['remaining_visit_store'] -= 1;
                                        $sales_schedule['store'][] = $store;
                                        $sales_schedules[$index2] = $sales_schedule;
                                        $sales[$index]['schedule'] = $sales_schedules;

                                        $store['visit_remaining'] -= 1;
                                        $store['visited_date'][] = $date;
                                        sort($store['visited_date'], SORT_ASC);
                                        $stores[$index3] = $store;

                                        $is_sales_visited_a_store = true;

                                        break;
                                    }


                                }

                            }

                            if($is_sales_visited_a_store) {

                                break;
                            }
                        }
                    }
                }

                $is_all_visited = true;
                foreach($stores as $index => $store) {
                    if($store['visit_remaining'] > 0) {
                        $is_all_visited = false;
                        break;
                    }
                }
            }

            echo "<table cellpadding='10' cellspacing='10' border='1'>";
            echo "<tr>";
            echo "<td>Sales</td>";
            for($i=1;$i<=$total_date_in_month;$i++) {
                $month = date("m", strtotime($this->date));
                $year = date("Y", strtotime($this->date));
                $date = str_pad($i,2,"0",STR_PAD_LEFT);
                echo "<td>".$date."-".$month."-".$year."</td>";
            }
            echo "</tr>";
            for($i=0;$i<count($sales);$i++) {
                echo "<tr>";
                echo "<td>".$sales[$i]['name']."</td>";
                for($j=1;$j<=$total_date_in_month;$j++) {
                    $current_date = str_pad($j,2,"0",STR_PAD_LEFT);
                    $sales_schedules = $sales[$i]['schedule'];
                    $is_empty = true;
                    foreach($sales_schedules as $index => $sales_schedule) {
                        if(date("d",strtotime($sales_schedule['date'])) == $current_date) {
                            echo "<td>";
                            echo "<ol>";
                            $stores = $sales_schedule['store'];
                            foreach($stores as $index => $store) {
                                echo "<li>".$store['name']." - ".$store['interval']."</li>";
                            }

                            echo "</ol>";
                            echo "</td>";
                            $is_empty = false;

                            break;
                        }
                    }
                    if($is_empty) {
                        echo "<td>-</td>";
                    }
                }
                echo "</tr>";
            }
            echo "</table>";
        }
    }

    private function distance($lat1, $lon1, $lat2, $lon2,)
    {
        if (($lat1 == $lat2) && ($lon1 == $lon2)) {
            return 0;
        } else {
            $theta = $lon1 - $lon2;
            $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
            $dist = acos($dist);
            $dist = rad2deg($dist);
            $miles = $dist * 60 * 1.1515;

            return ($miles * 1.609344);

        }
    }

    private function countMondayInMonth($date) {
        $month = date("m", strtotime($date));;
        $year = date("Y", strtotime($date));
        $count_days = date("t", strtotime($date));

        $total_monday = 0;

        for($i = 1; $i <= $count_days; $i++) {
            $dayISONumeric = date("N", strtotime($year . "-" . $month . "-" . $i));
            if($dayISONumeric == 1) {
                $total_monday+=1;
            }
        }

        return $total_monday;
    }
}
