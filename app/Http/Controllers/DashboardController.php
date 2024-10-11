<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DashboardController extends Controller
{
    public $headQuarterLatitude = "-7.9826"; //Latitude kantor pusat
    public $headQuarterLongitude = "112.6308"; //Longitude kantor pusat
    public $countSales = 10; //jumlah sales
    public $totalMaxVisitStore = 30; //jumlah maksimum kunjungan toko oleh tiap sales
    public $date = "2024-10-01"; //tanggal yang diinginkan

    public function index() {
        return view('dashboard');
    }

    private function createListStoreFromCSV($file_content) {
        // fungsi ini digunakan untuk membuat daftar toko dari file csv

        //pisahkan data berdasarkan karakter new line
        $data_no_linebreak = explode("\n", $file_content);
        //buang array pada baris teratas karena tidak mengandung data
        $data_no_linebreak = array_slice($data_no_linebreak, 1, -1);

        //inisialisasi array toko yang valid dan tidak valid
        $stores = array();
        $invalid_stores = array();

        //baca data csv yang sudah dipisahkan per baris
        foreach ($data_no_linebreak as $index => $value) {
            //pisahkan data berdasarkan koma
            $explode = explode(",", $value);
            //Hitung total hari senin pada bulan yang sudah ditentukan
            $total_monday = $this->countMondayInMonth($this->date);

            /*
             * Hasil index dan isi dari explode data csv ($explode)
             * 0 => nama toko
             * 1 => kode toko
             * 2 => latitude
             * 3 => longitude
             * 4 => alamat
             * 5 => kabupaten dan kode pos
             * 6 => jenis kunjungan toko
             */

            //tentukan total kunjungan yang diperlukan setiap toko berdasarkan jenisnya
            switch (trim(strtolower($explode[6]))) { //$explode[6] adalah index berisikan jenis kunjungan dari data yang sudah dipisahkan koma
                case "weekly": //tiap minggu
                    $remaining_visit = $total_monday; //total kunjungan adalah total hari senin dalam bulan yang sudah ditentukan
                    break;
                case "biweekly": //tiap 2 minggu
                    $remaining_visit = $total_monday / 2;  //total kunjungan adalah total hari senin dibagi dua dalam bulan yang sudah ditentukan
                    break;
                case "monthly": //1 bulan sekali
                    $remaining_visit = 1;
                    break;
                default:
                    $remaining_visit = 0;
                    break;
            }

            //jika latitude dan longitude tidak sama dengan 0, maka dianggap data toko valid
            $stores[] = array(
                'name' => trim($explode[0]),
                'code' => trim($explode[1]),
                'longitude' => $explode[2],
                'latitude' => $explode[3],
                'address' => trim($explode[4]),
                'postal_code' => trim($explode[5]),
                'interval' => strtolower(trim($explode[6])),
                'visit_remaining' => $remaining_visit,
                'visited_date' => array(), //daftar tanggal kunjungan yang sudah dilakukan oleh sales
                'km_distance_from_headquarter' => $this->distance($this->headQuarterLatitude, $this->headQuarterLongitude, $explode[3], $explode[2]), //perhitungan jarak dari toko dengan kantor
            );

        }

        //urutkan data toko berdasarkan jarak dari kantor ke toko dimulai yang terdekat hingga terjauh
        $keys = array_column($stores, 'km_distance_from_headquarter');
        array_multisort($keys, SORT_ASC, $stores);


        return $stores;
    }

    private function createListSales() {
        //fungsi ini digunakan untuk membuat daftar sales

        //inisialiasi array sales
        $sales = array();
        //buat data dummy sales
        for ($i = 0; $i < $this->countSales; $i++) {
            $sales[] = array(
                'code' => str_pad($i + 1, 5, "0", STR_PAD_LEFT), //buat kode sales dengan 5 digit angka
                'name' => "Sales " . ($i + 1), //nama sales
                'schedule' => array(), //digunakan untuk mencatat jadwal kunjungan sales
            );
        }

        return $sales;
    }

    private function generateScheduleSales($sales) {
        //fungsi ini digunakan untuk membuat kerangka daftar tanggal kunjugan tiap sales

        $total_date_in_month = date("t", strtotime($this->date)); //menghitung banyak nya hari pada tanggal yang sudah ditentukan
        for($i=0;$i<count($sales);$i++) { //perulangan sebanyak data sales dalam hal ini 10 orang
            for($j=1;$j<=$total_date_in_month;$j++) { //perulangan sebanyak hari (oktober terdapat 31 hari)
                $month = date("m", strtotime($this->date)); //mengambil 2 digit angka bulan dari tanggal yang sudah ditentukan
                $year = date("Y", strtotime($this->date)); //mengambil 4 digit angka tahun dari tanggal yang sudah ditentukan
                $iso_numeric_date = date("N", strtotime($year."-".$month."-".$j)); //mengambil informasi hari ke N pada tanggal yang sudah ditentukan

                /*
                 * iso numeric akan menampilkan angka dari 1 - 7 dari tanggal yang sudah ditentukan
                 * angka 1 => senin,
                 * dst
                 * angka 7 => minggu
                 */

                //jika angka yang dihasilkan tidak sama dengan 7 maka buat kerangka daftar kunjungan pada tanggal tersebut
                if($iso_numeric_date != 7) {
                    $sales[$i]['schedule'][] = array(
                        'date' => $year . "-" . $month . "-" . str_pad($j,2,"0",STR_PAD_LEFT), //tanggal
                        'remaining_visit_store' => $this->totalMaxVisitStore, //digunakan sebagai batas maksimum kunjungan yang dapat dilakukan sales dalam 1 hari (1 hari maks 30 kunjungan)
                        'store' => array(), //daftar toko yang akan dikunjungi
                    );
                }
            }
        }
        return $sales;
    }

    private function createTable($sales, $store) {
        $total_date_in_month = date("t", strtotime($this->date)); //menghitung banyak nya hari pada tanggal yang sudah ditentukan

        $html = "<table class='table table-responsive table-striped table-hover'>";
        $html .= "<thead><tr>";
        $html .= "<td>Sales</td>";
        //Membuat header tanggal
        for($i=1;$i<=$total_date_in_month;$i++) {
            $month = date("m", strtotime($this->date));
            $year = date("Y", strtotime($this->date));
            $date = str_pad($i,2,"0",STR_PAD_LEFT);
            $html .= "<td>".$date."-".$month."-".$year."</td>";
        }
        $html .= "</tr></thead><tbody>";
        //Perulangan sebanyak jumlah sales
        for($i=0;$i<count($sales);$i++) {
            $html .= "<tr>";
            $html .= "<td>".$sales[$i]['name']."</td>";
            //Perulangan sebanyak tanggal
            for($j=1;$j<=$total_date_in_month;$j++) {
                $current_date = str_pad($j,2,"0",STR_PAD_LEFT);
                $sales_schedules = $sales[$i]['schedule'];
                $is_empty = true;
                //Perulangan sebanyak jadwal kunjungan sales
                foreach($sales_schedules as $index => $sales_schedule) {
                    //Periksa apakah tanggal yang sedang dalam perulangan cocok dengan tanggal yang ada di jadwal kunjungan sales
                    if(date("d",strtotime($sales_schedule['date'])) == $current_date) {
                        $html .= "<td>";
                        $html .= "<ol>";
                        $stores = $sales_schedule['store'];
                        foreach($stores as $index => $store) {
                            $html .= "<li>".$store['name']."</li>";
                        }

                        $html .= "</ol>";
                        $html .= "</td>";
                        $is_empty = false;

                        break;
                    }
                }
                if($is_empty) {
                    $html .= "<td>-</td>";
                }
            }
            $html .= "</tr>";
        }
        $html .= "</tbody></table>";

        return $html;
    }

    private function isAllStoreAlreadyVisited($stores) : bool {
        //cek apakah semua toko telah habis dikunjungi atau belum
        $is_all_visited = true;
        foreach($stores as $index => $store) {
            if($store['visit_remaining'] > 0) {
                $is_all_visited = false;
                break;
            }
        }

        return $is_all_visited;
    }

    private function checkStoreIsAlreadyVisitedBySameSales($sales_schedule, $store) {
        //cek apakah jadwal kunjungan sales yang diperiksa sudah terdapat toko yang akan dikunjungi atau belum
        $can_visit_store = true;
        foreach ($sales_schedule['store'] as $index4 => $sales_store) {
            if ($sales_store['code'] == $store['code']) {
                $can_visit_store = false;
                $err_message = $sales_schedule['date']." - Sudah divisit oleh sales yang sama";
                break;
            }
        }
        return $can_visit_store;
    }

    private function checkStoreIsAlreadyVisitedAtSpesificDate($store, $date) {
        //cek apakah toko sudah dikunjungi pada tanggal yang sudah ditentukan
        $can_visit_store = true;
        if(count($store['visited_date'])>0) {
            $store_visited_dates = $store['visited_date'];
            foreach ($store_visited_dates as $index4 => $store_visited_date) {
                if ($store_visited_date == $date) {
                    $can_visit_store = false;
                    $err_message = "$date - Sudah pernah divisit di tanggal yang sama";
                    break;
                }
            }
        }
        return $can_visit_store;
    }

    private function checkIntervalVisitStore($store, $date) {
        //cek interval kunjungan toko apa sudah memenuhi syarat atau belum jika tanggal sekarang akan dilakukan kunjungan kembali.
        $can_visit_store = true;
        if(count($store['visited_date'])>0) {

            $visited_dates = $store['visited_date'];
            $diff_day_last_visit_second = 0;

            //menghitung interval tanggal sekarang dengan tanggal kunjungan terakhir toko
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
        return $can_visit_store;
    }

    private function checkPossibleDateForFirstTimeVisitStore($store, $date, $month, $year) {
        //cek apakah tanggal sekarang dimungkinkan untuk jadi kunjungan pertama kali pada toko yang memiliki interval biweekly dan weekly

        $can_visit_store = true;
        $store_visited_dates = $store['visited_date'];
        if (count($store_visited_dates) == 0 && $store['visit_remaining'] != 0) {
            if ($store['interval'] == "biweekly") {

                $temp_date = $date;
                //perulangan sebanyak total kunjungan yang diharuskan oleh toko
                for ($j = 1; $j < $store['visit_remaining']; $j++) {
                    //tambahkan 14 hari kedepan dari tanggal sekarang
                    $newDate = date('Y-m-d', strtotime($temp_date . " +14 days"));
                    //periksa apakah tanggal 14 hari kedepan masih dibulan yang sama atau tidak
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
                //perulangan sebanyak total kunjungan yang diharuskan oleh toko
                for ($j = 1; $j <= $store['visit_remaining']; $j++) {
                    //tambahkan 7 hari kedepan dari tanggal sekarang
                    $newDate = date('Y-m-d', strtotime($temp_date . " +7 days"));
                    //periksa apakah tanggal 7 hari kedepan masih dibulan yang sama atau tidak
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

        return $can_visit_store;
    }

    public function store(Request $request) {
        set_time_limit(300); //ubah limit eksusi script php menjadi 300 detik (untuk menghindari time out ketika sedang memproses data)

        if($request->hasFile('file')) {

            $file = $request->file('file');
            $logic = $request->input("logic");

            if($logic == "") {
                return redirect()->route('dashboard')->with('error',"Logika belum dipilih");
            }

            $file_content = file_get_contents($file->getRealPath()); //mendapatkan isi dari file csv yang sudah diupload

            $stores = $this->createListStoreFromCSV($file_content);
            $sales = $this->createListSales();
            $sales = $this->generateScheduleSales($sales);

            if($stores == null) {
                return redirect()->route('dashboard')->with('error',"Terdapat kesalahan dalam pembuatan daftar toko, cek file");
            }


            $table_html = "";

            if($logic == "a") {
                $table_html = $this->logicA($sales, $stores);
            } else {
                $table_html = $this->logicB($sales, $stores);
            }

            $data = array(
                'table_html' => $table_html,
                'logic' => $logic,
            );
            return view('result', $data);
        } else {
            return redirect()->route('dashboard')->with('error',"File CSV belum dipilih");
        }
    }

    private function logicB($sales, $stores) {
        $total_date_in_month = date("t", strtotime($this->date)); //menghitung banyak nya hari pada tanggal yang sudah ditentukan

        $is_all_visited = false; //indikator untuk menentukan apakah semua toko sudah dikunjungi atau belum
        $cur_index_store = 0; //index untuk membaca detail toko di dalam array toko

        //lakukan perulang selama toko masih belum dikunjungi
        while(!$is_all_visited) {

            //perulangan sebanyak total hari dari tanggal yang sudah dtentukan (31 hari di bulan oktober 2024)
            for($i=1;$i<=$total_date_in_month;$i++) {

                $month = date("m", strtotime($this->date)); //mengambil 2 digit angka bulan dari tanggal yang sudah ditentukan
                $year = date("Y", strtotime($this->date)); //mengambil 4 digit angka tahun dari tanggal yang sudah ditentukan
                $date = $year . "-" . $month . "-" . str_pad($i,2,"0",STR_PAD_LEFT); //buat tanggal berdasarkan perulangan (contoh 2024-10-01)

                //perulangan sebanyak total sales
                foreach($sales as $index => $value) {

                    $sales_schedules = $value['schedule']; //ambil jadwal kunjungan sales

                    //perulangan sebanyak jadwal sales yang sedang diproses
                    /*
                        Contoh struktur data $sales_schedules :

                        $sales[$i]['schedule'][] = array(
                        'date' => '2024-10-01'
                        'remaining_visit_store' => 30
                        'store' => array(),
                        );
                     */
                    foreach($sales_schedules as $index2 => $sales_schedule) {

                        /*
                            Contoh struktur data $sales_schedule :

                            $sales_schedule = array(
                                'date' => '2024-10-01'
                                'remaining_visit_store' => 30
                                'store' => array(),
                            );
                         */

                        //periksa apakah jadwal kunjungan sales terdapat tanggal 2024-10-01, dan sisa kunjungan yang dimiliki lebih dari 0
                        if($sales_schedule['date'] == $date && $sales_schedule['remaining_visit_store'] != 0) {

                            //Jika jadwal kunjungan sales sudah tidak kosong pada tanggal 2024-10-01
                            if( count($sales_schedule['store']) > 0) {

                                $cur_index_store = 0;

                                $last_visit_store = $sales_schedule['store'][count($sales_schedule['store']) - 1];

                                $diff_distance = null;
                                $nearest_store = null;
                                $last_distance_with_last_store = 0;

                                foreach ($stores as $index3 => $store) {

                                    if ($store['visit_remaining'] > 0) {
                                        $can_visit_store = true;
                                        if ($last_distance_with_last_store == 0) {
                                            // cek apakah toko terakhir yang dikunjungi sama atau tidak dengan store yang akan di looping
                                            if($last_visit_store['code'] != $store['code']) {
                                                $diff_distance = $this->distance($store['latitude'], $store['longitude'], $last_visit_store['latitude'], $last_visit_store['longitude']);
                                            } else {
                                                $can_visit_store = false;
                                            }
                                        } else {
                                            // cek apakah toko yang dianggap terdekat dengan toko yang dikunjungi terakhir sama atau tidak dengan toko yang akan di looping
                                            if ($nearest_store['code'] != $store['code']) {
                                                $diff_distance = $this->distance($store['latitude'], $store['longitude'], $nearest_store['latitude'], $nearest_store['longitude']);
                                            } else {
                                                $can_visit_store = false;
                                            }
                                        }

                                        if ($can_visit_store) {
                                            //cek apakah toko sudah termasuk di jadwal kunjungan sales
                                            $can_visit_store = $this->checkStoreIsAlreadyVisitedBySameSales($sales_schedule, $store);
                                        }
                                        if ($can_visit_store) {
                                            //cek apakah toko sudah dikunjungi di tanggal ini
                                            $can_visit_store = $this->checkStoreIsAlreadyVisitedAtSpesificDate($store, $date);
                                        }
                                        if($can_visit_store) {
                                            //cek kunjungan interval toko apa sudah memenuhi syarat atatu tidak (weekly, biweekly, monthly)
                                            $can_visit_store = $this->checkIntervalVisitStore($store, $date);
                                        }
                                        if ($can_visit_store) {
                                            //cek apakah tanggal sekarang mengakomidir untuk dijadikan kunjungan pertama toko (weekly, biweekly)
                                            $can_visit_store = $this->checkPossibleDateForFirstTimeVisitStore($store, $date, $month, $year);
                                        }

                                        //cek apakah toko yang diperiksa jarak tempuh nya lebih kecil atau tidak
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
                                    //jika kunjungan dapat dilakukan dan sudah menemukan toko terdekat, maka simpan ke data kunjungan sales

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

                                /*
                                 * Segmen program ini berfungsi untuk memasukkan toko sebanyak 1 buah dari daftar toko ke tiap sales pada tanggal 1
                                 *
                                 * Jika daftar kunjungan sales masih kosong, maka masukkan toko di index ke 0 dst
                                 */

                                $store = $stores[$cur_index_store];

                                $sales_schedule['remaining_visit_store'] -= 1; //jumlah sisa kunjungan sales ke toko berkurang 1

                                $sales_schedule['store'][] = $store; //masukan detail toko ke jadwal kunjungan sales
                                $sales_schedules[$index2] = $sales_schedule;
                                $sales[$index]['schedule'] = $sales_schedules; //simpan kembali data jadwal kunjungan yang sudah diubah ke sales terkait

                                $store['visit_remaining'] -= 1; //jumlah kebutuhan kunjungan tiap toko berkurang 1 (weekly, biweekly, monthly)
                                $store['visited_date'][] = $date; //masukkan tanggal kunjungan yang akan dilakukan sales
                                $stores[$cur_index_store] = $store; //simpan kembali data toko yang sudah diubah ke daftar toko

                                $cur_index_store++;
                                break;
                            }
                        }

                    }
                }

            }

            /*
             * Lakukan pengecekkan terhadap semua toko apakah jumlah kebutuhan kunjungan masih ada atau tidak
             */
            $is_all_visited = $this->isAllStoreAlreadyVisited($stores);
        }
        $html = $this->createTable($sales, $stores);
        return $html;
    }


    public function logicA($sales, $stores) {
        $total_date_in_month = date("t", strtotime($this->date)); //menghitung banyak nya hari pada tanggal yang sudah ditentukan

        $is_all_visited = false; //indikator untuk menentukan apakah semua toko sudah dikunjungi atau belum
        $cur_index_store = 0; //index untuk membaca detail toko di dalam array toko

        //lakukan perulang selama toko masih belum dikunjungi
        while(!$is_all_visited) {
            for($i=1;$i<=$total_date_in_month;$i++) {
                $month = date("m", strtotime($this->date));
                $year = date("Y", strtotime($this->date));
                $date = $year . "-" . $month . "-" . str_pad($i,2,"0",STR_PAD_LEFT);

                //perulangan sales
                foreach($sales as $index => $value) {
                    $sales_schedules = $value['schedule'];
                    $is_sales_visited_a_store = false;

                    //perulangan jadwal sales
                    foreach($sales_schedules as $index2 => $sales_schedule) {

                        //cek apakah sales memiliki jatah kunjungan toko pada tanggal ini
                        if($sales_schedule['date'] == $date && $sales_schedule['remaining_visit_store'] != 0) {

                            //perulangan tiap toko
                            foreach($stores as $index3 => $store) {
                                $can_visit_store = true;

                                //cek apakah toko masih memiliki jumlah kunjungan
                                if ($store['visit_remaining'] <= 0) {
                                    $can_visit_store = false;
                                }

                                if ($can_visit_store) {
                                    //cek apakah toko sudah dikunjungi di tanggal ini
                                    $can_visit_store = $this->checkStoreIsAlreadyVisitedAtSpesificDate($store, $date);
                                }
                                if ($can_visit_store) {
                                    //cek apakah tanggal sekarang mengakomidir untuk dijadikan kunjungan pertama toko (weekly, biweekly)
                                    $can_visit_store = $this->checkPossibleDateForFirstTimeVisitStore($store, $date, $month, $year);
                                }
                                if($can_visit_store) {
                                    //cek kunjungan interval toko apa sudah memenuhi syarat atatu tidak (weekly, biweekly, monthly)
                                    $can_visit_store = $this->checkIntervalVisitStore($store, $date);
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

                        //Jika sales sudah mengunjungi toko, berhenti perulangan, lanjut ke sales berikutnya
                        if($is_sales_visited_a_store) {
                            break;
                        }
                    }
                }
            }
            //cek apakah semua toko sudah selesai dikunjungi apa belum
            $is_all_visited = $this->isAllStoreAlreadyVisited($stores);
        }
        $html = $this->createTable($sales, $stores);
        return $html;
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
