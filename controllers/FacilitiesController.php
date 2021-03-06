<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use app\models\Facilities;
use app\models\FacilitiesSearch;
use app\models\Tokens;
use app\models\Log;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\helpers\Json;
use yii\web\Response;
use yii\web\Session;
use yii\web\Cors;

/**
 * FacilitiesController implements the CRUD actions for Facilities model.
 */
class FacilitiesController extends Controller
{

	public $hall;
	public $buildingId;
	public $srcInt;

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],

            'access' => [
                'class' => AccessControl::className(),
                'only' => ['index', 'view', 'create', 'update', 'delete'],
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],

            // For cross-domain AJAX request
            'corsFilter'  => [
                'class' => \yii\filters\Cors::className(),
                'cors'  => [
                    // restrict access to domains:
                    //'Origin'                           => static::allowedDomains(),
					'Origin' => ['http://192.168.0.108','http://localhost','*'],
					'Access-Control-Request-Method' => ['GET', 'POST', 'OPTIONS', '*'],
					// Allow only POST and PUT methods
					'Access-Control-Request-Headers' => ['*'],
					// Allow only headers 'X-Wsse'
					'Access-Control-Allow-Credentials' => null,
					// Allow OPTIONS caching
					'Access-Control-Max-Age' => 86400,
					// Allow the X-Pagination-Current-Page header to be exposed to the browser.
					'Access-Control-Expose-Headers' => ['X-Pagination-Current-Page'],

                ],
            ],

        ];
    }

    public function actionInfo()
	{
		return \Yii::createObject([
			'class' => 'yii\web\Response',
			'format' => \yii\web\Response::FORMAT_JSON,
			'data' => [
				'message' => 'hello world',
				'code' => 100,
			],
		]);
	}

	private function doLogEntry() {

		//if ( json_encode($_REQUEST) == '{"{\"fetch\":\"first\",\"bldg\":\"0019\",\"host\":\"map_pratt_edu\",\"token\":\"fa273d7803df815346eaa7da7425ec\",\"hash\":\"qwbka\"}":""}' ) {
		//	return true;
		//}

		if (strpos(json_encode($_REQUEST), '\"fetch\":\"first\"') > 0) {
			return true;
		}

		if ($_SERVER['REMOTE_ADDR'] == '172.16.77.5') {
		//	return true;
		}

	    $log = new Log();
    	$log->remote_addr		= $_SERVER['REMOTE_ADDR'];
    	$log->user_agent		= $_SERVER['HTTP_USER_AGENT'];
    	$log->request_method	= $_SERVER['REQUEST_METHOD'];
		$log->server_info		= json_encode($_SERVER);
		$log->session_info		= json_encode(Yii::$app->session);
    	$log->request_info		= json_encode($_REQUEST);

    	if($log->save(false)){
			return true;
    	} else {
			$arr['success'] = false;
			$arr['message'] = 'Error. Log entry not saved.';
			echo json_encode($arr);
			die();
    	}
	}

	private function checkToken() {

		if ($_SERVER['REQUEST_METHOD']=='GET'){
			$reqtokn = addslashes($_GET['token']);
		};
		if ($_SERVER['REQUEST_METHOD']=='POST'){
			$reqtokn = addslashes($_POST['token']);
		};

		if ($reqtokn = '65b22c76497f3b4c4436bf324e6154') {
			return true;
		}

		$token = Tokens::find()
			->where('token = :token', [':token' => $reqtokn])
			->andWhere('create_date > :create_date', [':create_date' => date('Y-m-d')])
			->one();

		if (date('Y-m-d',strtotime($token->attributes['create_date'])) != date('Y-m-d')) {

			$arr['request'] = $_REQUEST;
			$arr['match'] = $token->attributes;
			$arr['date'] = date('Y-m-d');
			$arr['cdate'] = date('Y-m-d',strtotime($token->attributes['create_date']));
			$arr['success'] = false;
			$arr['message'] = 'invalid token';

			header('Content-Type: application/json');
			echo json_encode($arr);
			die();
		}

	}

	private function checkRemote($remote) {

		return true;

		if (strpos($remote, ALLOWED_IPS) === false) {
			$arr['success'] = false;
			$arr['remote'] = $remote;
			$arr['message'] = 'Error. Remote address not recognized.';
			echo json_encode($arr);
			die();
		} else {
			return true;
		}
	}

	public function actionLoad() {

		echo 'loads done';
		die();
		return true;

		$path = '/Users/iancampbell/Sites/points_sample.json';

		$myfile = fopen($path, "r") or die("Unable to open file!");
		$contents = fread($myfile,filesize($path));

		$obj = json_decode($contents, true);

		foreach ($obj['features'] as $key=>$value) {

			if ($value['properties']['SUITE']) {

				echo "<p>";
				// 	echo '<br>Building: '.$bldg = $value['properties']['buildingId'];
				// 	echo '<br>Floor: '.$floor = $value['properties']['floorId'];
				// 	echo '<br>Room: '.$room = $value['properties']['SUITE'];

				$bldg = $value['properties']['buildingId'];
				$floor = $value['properties']['floorId'];
				$room = $value['properties']['SUITE'];

				$label = $value['properties']['label'];
				$label = mb_convert_encoding($label, 'UTF-8', 'UTF-8');
				$label = iconv("UTF-8", "UTF-8//IGNORE", $label);
				$label = preg_replace("/[^\\x00-\\xFFFF]/", "", $label);
				$label = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $label);

				$category = $value['properties']['CATEGORY'];

				//$label_exp = explode(',',$label);
				//$label_exp = explode(' ',$label);
				$label_exp = explode('.',$label);
				$label0 = addslashes($label_exp[0]);

				$sql = "
					select
						id,
						bldg_abbre,
						room_no,
						new_room_no,
						room_name,
						gk_bldg_id,
						gk_floor_id,
						latitude,
						longitude

					from facilities
					where gk_bldg_id = '$bldg'
					and gk_floor_id = '$floor'
					/*and (room_no like '%$room%' or new_room_no like '%$room%' or room_name like '%$label0%')*/
					/*and (room_no like '%$room%' or new_room_no like '%$room%')*/

					and (room_name like '%$category%')

					";

				$connection = Yii::$app->getDb();
				$command = $connection->createCommand($sql);

				$result = $command->queryAll();

				$rowCount = count($result);

				if ($result[0]) {

					foreach($result as $key1=>$value1) {

						$id			= $value1['id'];
						$latitude	= $value['properties']['latitude'];
						$longitude	= $value['properties']['longitude'];

						if (strlen(strval($value1['latitude'])) < 12) {

							//echo "<pre>";
							//echo $key1;
							//print_r($value1);
							//echo "</pre>";

        					$model = $this->findModel($id);

        					//echo "<pre>";
        					//print_r($model);
        					//echo "</pre>";

							$model->latitude		= $latitude;
							$model->longitude		= $longitude;

							if($model->save(false)){
								echo '<br>saved '.$id;
								//die();
							} else {
								$arr['success'] = false;
								$arr['message'] = 'Error. Log entry not saved.';
								echo json_encode($arr);
								die();
							}
						}
					}

				} else {

					echo "<p>Not Found: ".$sql;
					echo "<br>".$bldg.' -- '.$floor;
					echo "<pre>";
					echo $key;
					print_r($value);
					echo "</pre>";
					//die();

				}
			}
		}
		fclose($myfile);
		die();
	}

	public function actionPut() {

		header('Content-Type: application/json');
		//echo json_encode($_POST);
		//die();

		foreach($_POST['info'] as $key=>$value) {
			if ($value == '-') {
				$_POST['info'][$key] = '';
			}
		}

		$arr['post'] = json_encode($_POST);

		$this->doLogEntry();
		$this->checkToken();
		$remote = $this->checkRemote($_SERVER['REMOTE_ADDR']);

		/* TODO remove this when going live */
    	header("Access-Control-Allow-Origin: *");

    	$model = $this->findModel($_POST['id']);

		//	$model->accessible			= addslashes($_POST['info']['accessible']);

		/// $model->bldg_abbre			= addslashes($_POST['info']['bldgAbbr']);
		/// $model->bldg_name			= addslashes($_POST['info']['bldgName']);
		/// $model->bldg_code			= ltrim(addslashes($_POST['info']['buildingId']),'0');
		/// $model->floor				= ltrim(addslashes($_POST['info']['floorId']),'0');

		/// $model->room_name			= addslashes($_POST['info']['label']);

		if ( is_numeric($_POST['info']['latitude']) ) {
			$model->latitude			= addslashes($_POST['info']['latitude']);
		}

		if ( is_numeric($_POST['info']['longitude']) ) {
			$model->longitude			= addslashes($_POST['info']['longitude']);
		}

		//
		/// $model->room_no				= addslashes($_POST['info']['roomNo']);
		/// $model->new_room_no			= addslashes($_POST['info']['newRoomNo']);
		//
		//  $model->gk_display			= addslashes($_POST['info']['gkDisplay']);
		$model->gk_display			= 'Y';
		//
		// 	$model->gk_category			= addslashes($_POST['info']['category']);
		// 	$model->gk_fontsize			= addslashes($_POST['info']['fontSize']);
		// 	$model->gk_partialpath		= addslashes($_POST['info']['partialPath']);
		// 	$model->gk_showoncreation	= addslashes($_POST['info']['showOnCreation']);
		// 	$model->gk_showtooltip		= addslashes($_POST['info']['showToolTip']);
		// 	$model->gk_tooltiptitle		= addslashes($_POST['info']['tooltipTitle']);
		// 	$model->gk_tooltipbody		= addslashes($_POST['info']['tooltipBody']);
		// 	$model->gk_type				= addslashes($_POST['info']['type']);

		//$model->gk_bldg_id		= addslashes($_POST['info']['buildingId']);
		//$model->gk_floor_id		= addslashes($_POST['info']['floorId']);

		//// if (addslashes($_POST['info']['bldgAbbr']) == 'SG') {
		////	$model->gk_bldg_id	= '0000';
		////	$model->gk_floor_id	= '0000';
		//// }

		if ($model->save(false)) {
			$arr['success']	= true;
			$arr['message']	= '';
			$arr['remote']	= $remote;
			//$arr['id']	= $model->id;
			//$arr['post']	= json_encode($_POST);
		} else {
			$arr['success'] = false;
			$arr['message'] = 'Error. Changes were not saved.';
		}

		header('Content-Type: application/json');
		echo json_encode($arr);
		die();
	}

	public function actionYay() {

		die();

		header("Access-Control-Allow-Origin: *");
		header("Access-Control-Allow-Credentials: true");

		header('Content-Type: application/json');

		//echo json_encode($_REQUEST);
		//echo $_REQUEST;
		//print_r($_REQUEST);
		//print_r($_REQUEST,true);

		//$request = Yii::$app->request;
		//$post = $request->post();

		$out[]['message'] = 'yay';
		//$out[] = json_encode($_GET, true);
		//$out[] = json_encode($_POST, true);
		//$out[] = json_encode($_REQUEST, true);
		//$out[] = json_encode($post, true);
		//$out[] = json_encode($request, true);

		echo '{"results":';
		echo json_encode($out);
		echo '}';

		//Yii::info('blah blah', 'own');

		die();
	}

	public function actionTest() {

		die();

		/// CREATE VIEW `maps`.`sections` AS SELECT * FROM `provost`.`sections`;
		/// CREATE VIEW `maps`.`school` AS SELECT * FROM `provost`.`school`;
		/// CREATE VIEW `maps`.`department` AS SELECT * FROM `provost`.`department`;
		/// CREATE VIEW `maps`.`subject` AS SELECT * FROM `provost`.`subject`;

		header("Access-Control-Allow-Origin: *");
		header('Content-Type: application/json');

		$request = Yii::$app->request;
		$post = $request->post();
		$params = print_r($_REQUEST, true);

		//$_GET['searchText'] = '4df4fv MXCH. 003 ITL';

		$searchText = addslashes($_GET['searchText']);
		$searchText = trim($_GET['searchText']);
		$searchText = preg_replace('/[^a-z\d ]/i', ' ', $searchText);
		$searchText = preg_replace('!\s+!', ' ', $searchText);

		$searchExp = explode(' ',$searchText);
		foreach($searchExp as $snip) {
			$warr[] = " S.room LIKE '%".$snip."%' ";
			$warr2[] = " F.bldg_name LIKE '%".$snip."%' OR F.room_no LIKE '%".$snip."%' OR F.new_room_no  LIKE '%".$snip."%' ";
		}
		$wlike = implode(' OR ',$warr);
		$wlike2 = implode(' OR ',$warr2);

		$sql = "
			SELECT
				S.*,
				C.description AS school_name,
				U.description AS subject_name
			FROM sections S
			LEFT JOIN school C ON C.code = S.school
			LEFT JOIN subject U ON U.code = S.subject
			WHERE ( $wlike )
			";

		$connection	= Yii::$app->getDb();
		$command	= $connection->createCommand($sql);
		$result		= $command->queryAll();
		$rowCount	= count($result);
		$line		= array();

		if ($result[0]) {

			foreach($result as $key=>$value) {

				foreach($value as $field=>$record) {
					$value[$field] = trim(addslashes($record));
				}

				$match = 0;
				foreach($searchExp as $val) {
					if (strpos('_'.$value['room'], $val) > 0) {
						$match++;
					}
				}

				if ($match < 2) {
					continue;
				}

				$rec = array();
				$rec['id']			= '"id":"'.$value['crn'].'"';
				$rec['title']		= '"title":"'.$value['title'].'"';
				$rec['room']		= '"room":"'.$value['room'].'"';
				$rec['course']		= '"course":"'.$value['semester'].'-'.$value['subject'].'-'.$value['section'].'"';
				$rec['instrucor']	= '"instructor":"'.$value['instructor_last'].'"';
				$rec['time']		= '"time":"'.$value['time'].'"';
				$rec['school']		= '"school":"'.$value['school_name'].'"';
				$rec['subject']		= '"subject":"'.$value['subject_name'].'"';

				$line[] = '{'.implode(',',$rec).'}';
			}
		}

		$sql = '';
		$result = '';

		$sql = "
			SELECT
				F.id,
				F.bldg_name,
				F.room_no,
				F.new_room_no,
				F.department,
				F.gk_department,
				concat(F.bldg_name,' ',F.room_no,' ',F.new_room_no) as room_string
			FROM facilities F
			WHERE ( $wlike2 )
			";

		$command	= $connection->createCommand($sql);
		$result		= $command->queryAll();
		$rowCount	= count($result);

		if ($result[0]) {

			foreach($result as $key=>$value) {

				foreach($value as $field=>$record) {
					$value[$field] = trim(addslashes($record));
				}

				$match = 0;
				foreach($searchExp as $val) {
					if (stripos('_'.$value['room_string'], $val) > 0) {
						$match++;
					}
				}

				if ($match < 2) {
					continue;
				}

				$value['room'] = $value['room_no'];
				if ($value['new_room_no'] != '') {
					$value['room'] = $value['new_room_no'];
				}

				$rec = array();
				$rec['id']			= '"id":"'.$value['id'].'"';
				$rec['title']		= '"title":"'.$value['bldg_name'].'"';
				$rec['room']		= '"room":"'.$value['room'].'"';
				$rec['course']		= '"course":"'.$value['department'].'"';
				$rec['time']		= '"time":"'.$value['room_string'].'"';

				$line[] = '{'.implode(',',$rec).'}';
			}
			echo $out = '['.implode(',',$line).']';
		}

		if (count($line)<1) {
			echo '[{"title":"No Matches!"}]';
		}

		die();

	}

	public function makeFacilitiesArray() {

		//$arr[] = 'Brooklyn Campus Library';/// -----
		//$arr[] = 'Activity Resource Center';/// -----

		//$arr[] = 'Computer Labs -- Activity Res Ctr';/// ARC d-03 -----
		//$arr[] = 'Computer Labs -- DeKalb Hall';/// ARC d-03 -----
		//$arr[] = 'Computer Labs -- East Hall';/// EAST 301 -----
		//$arr[] = 'Computer Labs -- Engineering';/// Engineering Building, 2nd Floor -----
		//$arr[] = 'Computer Labs -- Higgins Hall';/// Higgins Hall North, 2nd and 3rd Floors -----
		//$arr[] = 'Computer Labs -- Machinery';/// Machinery Building, 1st Floor 115 -----
		//$arr[] = 'Computer Labs -- Main Bldg';/// Main 330 -----

		$arr[] = 'Higgins Hall Lab';/// Higgins Hall North, 2nd and 3rd Floors -----
		$arr[] = 'Machinery Computer Labs (MCC)';/// Machinery Building, 1st Floor 115 -----
		$arr[] = 'Engineering Computer Lab (EDS)';/// Engineering Building, 2nd Floor -----
		$arr[] = 'Foundation Media Lab';/// Main 330 -----

		$arr[] = 'Digital Output Center';/// Engineering Building, 2nd Floor -----
		$arr[] = '3D Printing Center';/// Engineering Building, 2nd Floor, Room 211 ----- Steuben Room 315
		$arr[] = 'Engineering Computer Lab (EDS)';/// Engineering Building, 2nd Floor -----
		$arr[] = 'Foundation Media Lab';/// Main Building, 3rd Floor 36? -----
		$arr[] = 'Language Resource Center';/// DeKalb Hall, 4th Floor -----

		$arr[] = 'CNC Lab';/// Engineering LL -----
		$arr[] = 'Industrial Design Shop';/// -----
		$arr[] = 'Form and Tech Lab';/// PS rm 48 -----
		$arr[] = 'Laser Cutting';/// Steuben 315 -----
		$arr[] = 'Metal Shop';/// chem 3rd fl 306 -----
		$arr[] = 'Photo Studio';/// Steuben Hall Room 305 -----
		$arr[] = 'Plaster Shop';/// PS 5th fl rm 54 -----
		$arr[] = 'Rapid Prototyping Room';/// ps 4th floor -----
		$arr[] = 'Wood Shop';/// Pratt Studios 57 -----

		$arr[] = 'Interdisciplinary Technology Lab';/// Engr 002 -----

		return $arr;

	}






	public function actionAccessible() {

		$this->doLogEntry();

    	header("Access-Control-Allow-Origin: *");

		//$arr = $this->makeFacilitiesArray();

		$sql = "
			select
				F.id,
				F.bldg_abbre,
				F.bldg_name,
				F.gk_bldg_id,
				F.gk_floor_id,
				F.latitude,
				F.longitude,
				F.bldg_name,
				F.room_name,

				A.latitude as accessLat,
				A.longitude as accessLong,
				A.gk_bldg_id as accessBldgId,
				A.gk_floor_id as accessFlrId,
				A.overhead,
				A.zoom,
				A.rotation,
				A.type,
				A.legend_copy,

				B.latitude as bldgLat,
				B.longitude as bldgLong,

				C.latitude as elevLat,
				C.longitude as elevLong,

				C.gk_bldg_id as elevBldgId,
				C.gk_floor_id as elevFlrId

			from facilities F

			join access A on A.floor_fk = F.gk_floor_id

			join buildings B on B.gk_bldg_id = F.gk_bldg_id

			left join elevators E on F.id = E.entrance
			left join facilities C on E.elevator = C.id

			where F.accessible = 'Y'
			and F.gk_display != 'N'
			and F.room_name LIKE '%Entrance%'
			order by F.bldg_name asc, F.room_name asc ";

		$connection = Yii::$app->getDb();
		$command = $connection->createCommand($sql);
		$rows = $command->queryAll();
		$rowCount = count($result);

		if ($rows[0]) {
			$i = 0;

			foreach($rows as $row) {

				$exp = array();

				$exp = explode(' - ',$row['room_name']);

				if ($exp[1] != '') {
					$level = ' - '.$exp[1];
				} else {
					$level = '';
				}

				$type = ucwords($row['type']);

				$out['res'][$i]['type']			= 'Accessible';
				$out['res'][$i]['label_button']	= $row['bldg_name'].' '.$type;
				$out['res'][$i]['label_poi']	= 'Accessible Entrance';
				$out['res'][$i]['label_tooltip']= 'Accessible Entrance';

				$out['res'][$i]['bldg_id']		= $row['gk_bldg_id'];
				$out['res'][$i]['floor_id']		= $row['gk_floor_id'];
				$out['res'][$i]['room']			= 'room';
				$out['res'][$i]['name']			= $row['bldg_name'];
				$out['res'][$i]['code']			= $row['bldg_abbre'];
				$out['res'][$i]['dept']			= 'dept';
				$out['res'][$i]['on_off_campus']= $row['on_off_campus'];
				$out['res'][$i]['latitude']		= substr(trim($row['latitude']),0,10);
				$out['res'][$i]['longitude']	= substr(trim($row['longitude']),0,10);
				$i++;

				/*
				if ($row['elevLat'] != '') {

					$type = 'Elevator';

					$out['res'][$i]['type']			= 'Accessible';
					$out['res'][$i]['label_button']	= $row['bldg_name'].' '.$type;
					$out['res'][$i]['label_poi']	= $type;
					$out['res'][$i]['label_tooltip']= 'tooltip';

					$out['res'][$i]['bldg_id']		= $row['gk_bldg_id'];
					$out['res'][$i]['floor_id']		= $row['gk_floor_id'];
					$out['res'][$i]['room']			= 'room';
					$out['res'][$i]['name']			= $row['bldg_name'];
					$out['res'][$i]['code']			= $row['bldg_abbre'];
					$out['res'][$i]['dept']			= 'dept';
					$out['res'][$i]['on_off_campus']= $row['on_off_campus'];
					$out['res'][$i]['latitude']		= substr(trim($row['elevLat']),0,10);
					$out['res'][$i]['longitude']	= substr(trim($row['elevLong']),0,10);
					$i++;
				}
				*/

			}

			ksort($out);
		}
		header('Content-Type: application/json');
		echo json_encode($out);
		die();
	}



	public function actionLabs() {

		$this->doLogEntry();

    	header("Access-Control-Allow-Origin: *");

		$arr = $this->makeFacilitiesArray();

		$sql = " select
					id,
					bldg_abbre,
					gk_bldg_id,
					gk_floor_id,
					gk_department,
					latitude,
					longitude,
					room_no,
					new_room_no,
					on_off_campus
					from facilities
					where gk_display != 'N'
					and gk_department != '' and ( ";
		foreach($arr as $facility) {
			$like[] = " gk_department like '%$facility%' ";
		}
		$sql .= implode (' or ', $like) . ' )';
		$sql .= " or gk_school like '%research and centers%' ";

		$sql .= " and latitude != '' ";
		$sql .= " and gk_floor_id != '' ";
		//$sql .= " and (room_no !='' or new_room_no !='')";

		$connection = Yii::$app->getDb();
		$command = $connection->createCommand($sql);
		$result = $command->queryAll();
		$rowCount = count($result);

		if ($result[0]) {
			$i = 0;
			foreach($result as $key=>$value) {

				foreach($value as $field=>$record) {
					$value[$field] = trim($record);
				}

				$dept_exp = explode('|',$value['gk_department']);

				if (trim($value['new_room_no']) != '') {
					$room_number = trim($value['new_room_no']);
				} else {
					$room_number = trim($value['room_no']);
				}

				foreach($dept_exp as $dept) {
					$dept = trim($dept);
					if ($dept != '') {

						if ($filter[$dept] != '') {
							continue;
						}
						$out['res'][$i]['type']			= "Labs";
						$out['res'][$i]['label_button']	= $dept;
						$out['res'][$i]['label_poi']	= $dept."\n".$room_number;
						$out['res'][$i]['label_tooltip']= "Lab ".$dept."\nRoom ".$room_number;

						$out['res'][$i]['bldg_id']		= $value['gk_bldg_id'];
						$out['res'][$i]['floor_id']		= $value['gk_floor_id'];
						$out['res'][$i]['room']			= $room_number;
						$out['res'][$i]['name']			= $value['bldg_name'];
						$out['res'][$i]['code']			= $value['bldg_abbre'];
						$out['res'][$i]['dept']			= $dept;
						$out['res'][$i]['on_off_campus']= $value['on_off_campus'];
						$out['res'][$i]['latitude']		= substr(trim($value['latitude']),0,10);
						$out['res'][$i]['longitude']	= substr(trim($value['longitude']),0,10);
						$filter[$dept] = $dept;
						$i++;
					}
				}
			}
			ksort($out);
		}
		header('Content-Type: application/json');
		echo json_encode($out);
		die();
	}

	public function actionBuildings() {

		$this->doLogEntry();

    	header("Access-Control-Allow-Origin: *");

		$sql = " select
					bldg_name,
					bldg_abbre,
					on_off_campus,
					latitude,
					longitude,
					gk_bldg_id
				from facilities
				where bldg_code != ''
				and on_off_campus = 'ON'
				group by bldg_abbre
				order by bldg_abbre
				";

		$connection = Yii::$app->getDb();
		$command = $connection->createCommand($sql);
		$result = $command->queryAll();
		$rowCount = count($result);

		if ($result[0]) {

			$i = 0;

			foreach($result as $key=>$value) {

				foreach($value as $field=>$record) {
					$value[$field] = trim($record);
				}

				$out['res'][$i]['type']			= "Buildings";
				$out['res'][$i]['label_button']	= $value['bldg_name'];
				$out['res'][$i]['label_poi']	= $value['bldg_name'];
				$out['res'][$i]['label_tooltip']= $value['bldg_name'];

				$out['res'][$i]['bldg_id']		= $value['gk_bldg_id'];
				$out['res'][$i]['name']			= $value['bldg_name'];
				$out['res'][$i]['code']			= $value['bldg_abbre'];
				$out['res'][$i]['on_off_campus']= $value['on_off_campus'];
				$out['res'][$i]['latitude']		= substr(trim($value['latitude']),0,10);
				$out['res'][$i]['longitude']	= substr(trim($value['longitude']),0,10);

				$i++;
			}
		}

		header('Content-Type: application/json');
		echo json_encode($out);
		die();
	}

	public function actionAcademics() {
		$this->actionFacilities('Academics');
	}
	public function actionAdministration() {
		$this->actionFacilities('Administration');
	}

	public function actionFacilities($type) {

		//$type = addslashes($_GET['type']);

		$this->doLogEntry();

    	header("Access-Control-Allow-Origin: *");

		$arr = $this->makeFacilitiesArray();

		$sql = "
			select
				id,
				bldg_abbre,
				bldg_name,
				gk_bldg_id,
				gk_floor_id,
				gk_department,
				gk_school,
				on_off_campus,
				latitude,
				longitude,
				room_no,
				new_room_no

				from facilities
				where gk_display != 'N'
				and gk_department != ''
				and bldg_abbre != 'GATE'
				and ( ";
			foreach($arr as $facility) {
				$like[] = " gk_department not like '%$facility%' ";
			}
			$sql .= implode (' and ', $like) . ' )';
			$sql .= " and latitude != '' ";
			$sql .= " and gk_floor_id != '' ";
			$sql .= ' order by bldg_name asc ';

		$connection = Yii::$app->getDb();
		$command = $connection->createCommand($sql);
		$result = $command->queryAll();
		$rowCount = count($result);

		if ($result[0]) {
			$i = 0;
			foreach($result as $key=>$value) {
				foreach($value as $field=>$record) {
					$value[$field] = trim($record);
				}
				$dept_exp = explode('|',$value['gk_department']);

				if ($type == 'Academics' && $value['gk_school'] == '') {
					continue;
				}
				if ($type == 'Administration' && $value['gk_school'] != '') {
					continue;
				}

				foreach($dept_exp as $dept) {
					$dept = trim($dept);
					if ($dept != '') {

						if ($filter[$dept] != '') {
							continue;
						}
						if (trim($value['new_room_no']) != '') {
							$room_number = trim($value['new_room_no']);
						} else {
							$room_number = trim($value['room_no']);
						}

						$out['res'][$i]['type']				= $type;
						$out['res'][$i]['label_button']		= $dept;
						$out['res'][$i]['label_poi']		= $dept."\n".$room_number;
						$out['res'][$i]['label_tooltip']	= $type.": ".$dept."\nRoom: ".$room_number;


						$out['res'][$i]['bldg_id']			= $value['gk_bldg_id'];
						$out['res'][$i]['floor_id']			= $value['gk_floor_id'];
						$out['res'][$i]['room']				= $room_number;
						$out['res'][$i]['name']				= $value['bldg_name'];
						$out['res'][$i]['code']				= $value['bldg_abbre'];
						$out['res'][$i]['dept']				= $dept;
						$out['res'][$i]['on_off_campus']	= $value['on_off_campus'];
						$out['res'][$i]['latitude']			= substr(trim($value['latitude']),0,10);
						$out['res'][$i]['longitude']		= substr(trim($value['longitude']),0,10);
						$filter[$dept] = $dept;
						$i++;
					}
				}
			}
			ksort($out);
		}
		header('Content-Type: application/json');
		echo json_encode($out);
		die();
	}

	public function actionLdap($searchTerm) {

		die();

		$conn = ldap_connect(LDAPHOST, LDAPPORT) or die('ldap connection error ' . LDAPHOST . ' : ' . LDAPPORT);
		$bind = ldap_bind($conn, LDAPUSER, LDAPPASS);
		$fields = array('ou', 'title', 'telephoneNumber', 'roomNumber', 'sn', 'givenName', 'mail', 'employeeType', 'audio');

		$dn = 'o=My Company, c=US';
		$filter='( & (|(cn='.$searchTerm.'*)(mail='.$searchTerm.'*)(givenname='.$searchTerm.'*)(sn='.$searchTerm.'*)(displayName='.$searchTerm.'*)) (|(employeeType=Staff)(employeeType=Faculty)) )';
		$sr = ldap_search($conn, LDAPBASE, $filter, $fields);
		$info = ldap_get_entries($conn, $sr);
		$line = array();

		//print_r($info);

		if ($info['count'] > '0') {

			foreach($info as $key=>$val) {

				if (@$val['audio'][0] > '0') {
					continue;
				}

				if ($val['roomnumber'][0] == '') {
					continue;
				}

				//echo "<br> room number " . $val['roomnumber'][0];

				$exp1 = explode(',',$val['roomnumber'][0]);

				$on_off = "ON";
				if ($exp1[0] != 'Brooklyn Campus') {
					$on_off = "OFF";
					continue;
				}
				if ($exp1[1] == '') {
					continue;
				}

				$hall = trim($exp1[1]);

				$exp2 = explode(' ',$hall);

				$this->hall = $exp2[0].' '.$exp2[1];
				//$this->buildingId = $this->fetchBuildings();
				$this->buildingId = Yii::$app->mycomponent->fetchBuildings($this->hall);

				if ($this->buildingId == '') {
					continue;
				}

				//$coords = $this->fetchCoords();
				$coords = Yii::$app->mycomponent->fetchCoords($this->buildingId);
				$coordsExp = explode(',',$coords);

				$out[$this->srcInt]['type']			= 'Person';
				$out[$this->srcInt]['id']			= '';
				$out[$this->srcInt]['bldg_id']		= $this->buildingId;
				$out[$this->srcInt]['floor_id']		= '';
				$out[$this->srcInt]['bldg_name']	= $this->hall;
				$out[$this->srcInt]['bldg_abbre']	= '';
				$out[$this->srcInt]['room_no']		= $val['roomnumber'][0];
				$out[$this->srcInt]['room_name']	= $val['roomnumber'][0];
				$out[$this->srcInt]['on_off_campus']= $on_off;
				$out[$this->srcInt]['school']		= $val['ou'][0];
				$out[$this->srcInt]['department']	= $val['ou'][0];

				$out[$this->srcInt]['title']		= $val['title'][0];
				$out[$this->srcInt]['phone']		= $val['telephonenumber'][0];
				//$out[$this->srcInt]['office']		= $val['roomnumber'][0];
				$out[$this->srcInt]['lname']		= $val['sn'][0];
				$out[$this->srcInt]['fname']		= $val['givenname'][0];
				$out[$this->srcInt]['mail']			= $val['mail'][0];

				$out[$this->srcInt]['professor']	= '';
				$out[$this->srcInt]['course']		= '';
				$out[$this->srcInt]['times']		= '';

				$out[$this->srcInt]['sculpture']	= '';
				$out[$this->srcInt]['artist']		= '';
				$out[$this->srcInt]['latitude']		= $coordsExp[0];
				$out[$this->srcInt]['longitude']	= $coordsExp[1];

				//$out[$this->srcInt]['label']		= $hall;
				$out[$this->srcInt]['label_button']	= $val['givenname'][0].' '.$val['sn'][0];
				$out[$this->srcInt]['label_poi']	= $hall;

				$this->srcInt = $this->srcInt + 1;

				/// temporary to show only one result
				//return $out;
			}

		} else {

			$out[$this->srcInt]['type']		= 'Person';
			$out[$this->srcInt]['bldg_id']	= 'No Matches';
			$out[$this->srcInt]['message']	= 'No Matches in LDAP';
			$this->srcInt = $this->srcInt + 1;

		}

		if (count($out) < 1) {
			$out[$this->srcInt]['type']		= 'Person';
			$out[$this->srcInt]['bldg_id']	= 'No Matches';
			$out[$this->srcInt]['message']	= 'Building Not Found';
			$this->srcInt = $this->srcInt + 1;
		}

		return $out;
	}


	public function searchLdap($searchTerm) {

		$conn = ldap_connect(LDAPHOST, LDAPPORT) or die('ldap connection error ' . LDAPHOST . ' : ' . LDAPPORT);
		$bind = ldap_bind($conn, LDAPUSER, LDAPPASS);
		$fields = array('ou', 'title', 'telephoneNumber', 'roomNumber', 'sn', 'givenName', 'mail', 'employeeType', 'audio');

		$dn = 'o=My Company, c=US';
		$filter='( & (|(cn='.$searchTerm.'*)(mail='.$searchTerm.'*)(givenname='.$searchTerm.'*)(sn='.$searchTerm.'*)(displayName='.$searchTerm.'*)) (|(employeeType=Staff)(employeeType=Faculty)) )';
		$sr = ldap_search($conn, LDAPBASE, $filter, $fields);
		$info = ldap_get_entries($conn, $sr);
		$line = array();

		//print_r($info);

		if ($info['count'] > '0') {

			foreach($info as $key=>$val) {

				if (@$val['audio'][0] > '0') {
					continue;
				}

				if ($val['roomnumber'][0] == '') {
					continue;
				}

				//echo "<br> room number " . $val['roomnumber'][0];

				$exp1 = explode(',',$val['roomnumber'][0]);

				$on_off = "ON";
				if ($exp1[0] != 'Brooklyn Campus') {
					$on_off = "OFF";
					continue;
				}
				if ($exp1[1] == '') {
					continue;
				}

				$hall = trim($exp1[1]);

				$exp2 = explode(' ',$hall);

				$this->hall = $exp2[0].' '.$exp2[1];
				//$this->buildingId = $this->fetchBuildings();
				$this->buildingId = Yii::$app->mycomponent->fetchBuildings($this->hall);

				if ($this->buildingId == '') {
					continue;
				}

				//$coords = $this->fetchCoords();
				$coords = Yii::$app->mycomponent->fetchCoords($this->buildingId);
				$coordsExp = explode(',',$coords);

				$out[$this->srcInt]['type']			= 'Person';
				$out[$this->srcInt]['id']			= '';
				$out[$this->srcInt]['bldg_id']		= $this->buildingId;
				$out[$this->srcInt]['floor_id']		= '';
				$out[$this->srcInt]['bldg_name']	= $this->hall;
				$out[$this->srcInt]['bldg_abbre']	= '';
				$out[$this->srcInt]['room_no']		= $val['roomnumber'][0];
				$out[$this->srcInt]['room_name']	= $val['roomnumber'][0];
				$out[$this->srcInt]['on_off_campus']= $on_off;
				$out[$this->srcInt]['school']		= $val['ou'][0];
				$out[$this->srcInt]['department']	= $val['ou'][0];

				$out[$this->srcInt]['title']		= $val['title'][0];
				$out[$this->srcInt]['phone']		= $val['telephonenumber'][0];
				//$out[$this->srcInt]['office']		= $val['roomnumber'][0];
				$out[$this->srcInt]['lname']		= $val['sn'][0];
				$out[$this->srcInt]['fname']		= $val['givenname'][0];
				$out[$this->srcInt]['mail']			= $val['mail'][0];

				$out[$this->srcInt]['professor']	= '';
				$out[$this->srcInt]['course']		= '';
				$out[$this->srcInt]['times']		= '';

				$out[$this->srcInt]['sculpture']	= '';
				$out[$this->srcInt]['artist']		= '';
				$out[$this->srcInt]['latitude']		= $coordsExp[0];
				$out[$this->srcInt]['longitude']	= $coordsExp[1];

				//$out[$this->srcInt]['label']		= $hall;
				$out[$this->srcInt]['label_button']	= $val['givenname'][0].' '.$val['sn'][0];
				$out[$this->srcInt]['label_poi']	= $hall;
				$out[$this->srcInt]['label_tooltip']	= $val['givenname'][0].' '.$val['sn'][0];


				$this->srcInt = $this->srcInt + 1;

				/// temporary to show only one result
				//return $out;
			}

		} else {

			$out[$this->srcInt]['type']		= 'Person';
			$out[$this->srcInt]['bldg_id']	= 'No Matches';
			$out[$this->srcInt]['message']	= 'No Matches in LDAP';
			$this->srcInt = $this->srcInt + 1;

		}

		if (count($out) < 1) {
			$out[$this->srcInt]['type']		= 'Person';
			$out[$this->srcInt]['bldg_id']	= 'No Matches';
			$out[$this->srcInt]['message']	= 'Building Not Found';
			$this->srcInt = $this->srcInt + 1;
		}

		return $out;
	}


	public function searchFacilities($searchTerm) {

		$this->doLogEntry();

    	header("Access-Control-Allow-Origin: *");

		$sql = " select

					id,
					bldg_abbre,
					bldg_name,
					gk_bldg_id,
					gk_floor_id,
					gk_department,
					gk_school,
					on_off_campus,
					latitude,
					longitude,
					room_name,
					room_no,
					new_room_no,
					gk_sculpture_name,
					gk_sculpture_artist_first,
					gk_sculpture_artist_last

				from facilities

				where
					( bldg_name like '%".$searchTerm."%' or
					  room_name like '%".$searchTerm."%' or
					  gk_school like '%".$searchTerm."%' or
					  gk_department like '%".$searchTerm."%' or
					  gk_sculpture_name like '%".$searchTerm."%' or
					  gk_sculpture_artist_first like '%".$searchTerm."%' or
					  gk_sculpture_artist_last like '%".$searchTerm."%' )

					  and on_off_campus = 'ON'

					  and gk_display != 'N'

					  group by gk_floor_id, room_name, gk_sculpture_name

				";

		$connection = Yii::$app->getDb();
		$command = $connection->createCommand($sql);
		$result = $command->queryAll();
		$rowCount = count($result);

		$this->srcInt = 0;

		if ($result[0]) {

			foreach($result as $key=>$value) {

				foreach($value as $field=>$record) {
					$value[$field] = trim($record);
				}

				if (stripos('_'.$value['room_name'], 'blue light') > 0) {
					continue;
				}

				if (trim($value['new_room_no']) != '') {
					$room_number = trim($value['new_room_no']);
				} else {
					$room_number = trim($value['room_no']);
				}

				$room_number = trim($room_number,'-');

				if ($value['gk_bldg_id'] == '0000') {
					$room_number = '';
				}

				$out[$this->srcInt]['type']	= 'Place';

				$out[$this->srcInt]['id']			= $value['id'];
				$out[$this->srcInt]['bldg_id']		= $value['gk_bldg_id'];
				$out[$this->srcInt]['floor_id']		= $value['gk_floor_id'];
				$out[$this->srcInt]['bldg_name']	= $value['bldg_name'];
				$out[$this->srcInt]['bldg_abbre']	= $value['bldg_abbre'];
				$out[$this->srcInt]['room_no']		= $room_number;
				$out[$this->srcInt]['room_name']	= $value['room_name'];
				$out[$this->srcInt]['on_off_campus']= $value['on_off_campus'];
				$out[$this->srcInt]['school']		= $value['gk_school'];
				$out[$this->srcInt]['department']	= $value['gk_department'];

				$out[$this->srcInt]['title']		= '';
				$out[$this->srcInt]['phone']		= '';
				$out[$this->srcInt]['fname']		= '';
				$out[$this->srcInt]['lname']		= '';
				$out[$this->srcInt]['mail']			= '';

				$out[$this->srcInt]['professor']	= '';
				$out[$this->srcInt]['course']		= '';
				$out[$this->srcInt]['times']		= '';

				$out[$this->srcInt]['sculpture']	= $value['gk_sculpture_name'];
				$out[$this->srcInt]['artist']		= trim($value['gk_sculpture_artist_first'].' '.$value['gk_sculpture_artist_last']);
				$out[$this->srcInt]['latitude']		= substr(trim($value['latitude']),0,10);
				$out[$this->srcInt]['longitude']	= substr(trim($value['longitude']),0,10);

				//$out[$this->srcInt]['label']		= trim($value['bldg_name'].' '.$room_number);
				$out[$this->srcInt]['label_button']	= trim($value['bldg_name'].' '.$room_number);
				$out[$this->srcInt]['label_poi']	= trim($value['room_name']."\n".$value['bldg_name']."\n".$room_number);
				$out[$this->srcInt]['label_tooltip']	= trim($value['room_name']."\n".$value['bldg_name']."\n".$room_number);


				$this->srcInt = $this->srcInt + 1;

				/// temporary to show only one result
				//return $out;
			}

		} else {

			$out[$this->srcInt]['type']		= 'Place';
			$out[$this->srcInt]['bldg_id']	= 'No Matches';
			$out[$this->srcInt]['message']	= 'No Matches Found';
			$this->srcInt = $this->srcInt + 1;

		}

		return $out;
	}

	public function actionSections() {

		$request = Yii::$app->request;
		$post = $request->post();

		$params = array();

		$_POST['filter'] = $post['search'];
		$_GET['srcInt'] = $this->srcInt;

		return Yii::$app->runAction('sections/search', $params);
	}

	public function searchSections() {

		$request = Yii::$app->request;
		$post = $request->post();

		$params = array();

		$_POST['filter'] = $post['search'];
		$_GET['srcInt'] = $this->srcInt;

		return Yii::$app->runAction('sections/search', $params);
	}

	public function actionSearch() {

		header("Access-Control-Allow-Origin: *");
		header("Access-Control-Allow-Credentials: true");
		header('Content-Type: application/json');

		if ($_GET['search'] != '') {
			$_POST['search'] = addslashes($_GET['search']);
			$post['search'] = addslashes($_GET['search']);
		}

		$request = Yii::$app->request;
		$post = $request->post();

		$post['search'] = trim($post['search']);

		if ($post['search'] == '') {

			$out[0]['type']		= 'No Search String';
			$out[0]['bldg_id']	= 'No Output';
			$out[0]['message']	= 'No Output';

		} else {

			$out1 = $this->searchFacilities($post['search']);
			$out2 = $this->searchLdap($post['search']);
			$out3 = $this->searchSections($post['search']);
			$out = $out1 + $out2 + $out3;
			//$out = $out2;

		}

		//print_r($out);
		//print_r($out1);
		//print_r($out2);

		echo '{"res":';
		echo json_encode($out);
		echo '}';

		die();
	}

    public function actionPull() {

		$this->doLogEntry();
		$this->checkToken();

		$request = Yii::$app->request;
		$posts = $request->post();
		//$params = $request->bodyParams;
		//$stuff = print_r($_POST, true);

		///{"{\"

		//if (stripos('_'.$posts, '{"{') == '1') {

		if ($_POST['client']=='mobile') {

			if ($_POST['floor']=='') {
				die();
			}

			foreach($posts as $key=>$val) {
				$post[$key] = addslashes($val);
			}

		} else {

			foreach($posts as $key=>$val) {
				//Yii::info(' -------------------------- ', 'own');
				$thing = $key;
				$remove = array('{','}','"');
				$clean = str_replace($remove, '', $thing);
				$explode = explode(',',$clean);
				foreach($explode as $val) {
					$coln = explode(':',$val);
					$post[$coln[0]] = addslashes($coln[1]);
					//Yii::info($coln[0].' = '.$coln[1], 'own');
				}
			}

		}

		//} else {
		//	foreach($posts as $key=>$val) {
		//		$post[$key] = addslashes($val);
		//	}
		//}

		//Yii::info(' ========================= ', 'own');
		//Yii::info($post['token'], 'own');

		/* TODO remove this when going live */
    	header("Access-Control-Allow-Origin: *");

		$sql = " select
					F.*,
					C.copy
				from facilities F
				left join legend_copy C on C.id = F.legend
				";

		if ($post['provision'] != '') {

			$sql .= " where F.gk_space_provisions like '%".addslashes($post['provision'])."%' ";

		} elseif ($post['recordId'] != '') {

			$recordId = addslashes($post['recordId']);
			$sql .= " where F.id = '$recordId' ";

		} else {

			$sql .= " where F.department not in ('INACTIVE','UNUSABLE')
				and F.department not like '%inactive%'
				and F.major_category not like '%inactive%'
				and F.functional_category not like '%inactive%'
				and F.gk_display != 'N'
				and F.gk_bldg_id != ''
				and F.gk_floor_id != ''
				and F.room_name != ''
				and F.room_name not like '%inactive%'
				and F.room_name not like '%storage%'
				";

			if ($post['floor'] != '') {
				$sql .= " and F.gk_floor_id = '".addslashes($post['floor'])."' ";
			}

			if ($post['select'] == 'floor') {
				$sql .= " and F.gk_display = 'Y' ";
			}

			if ($post['building'] != '') {
				$bldg = addslashes($post['building']);
				$sql .= " AND F.bldg_abbre = '$bldg' ";
			}

			if ($post['accessible'] == 'Y') {
				$sql .= " AND F.accessible = 'Y' ";
			}

			if ($post['webapp']=='display') {
				$sql .= " AND F.gk_display != 'N' ";
			}

			if ($post['webapp']!='manage') {
				$sql .= " AND F.gk_display != 'N' ";
			} else {
				//$sql .= " AND length('latitude') < 11 ";
			}

			if ($post['bldg'] != '') {
				$bldg = addslashes($post['bldg']);
				$sql .= " AND (F.gk_bldg_id = '$bldg' or F.bldg_abbre = '$bldg') ";
			}

			if ($post['abbre'] != '') {
				$abbre = addslashes($post['abbre']);
				$sql .= " AND F.bldg_abbre = '$abbre' ";
			}

			if ($post['match']!='') {
				$sql .= " AND (F.room_name like '%".$post['match']."%' OR F.gk_sculpture_name like '%".$post['match']."%' OR F.gk_department like '%".$post['match']."%') ";
			}

			if ($post['webapp'] == 'manage') {
				$sql .= " ORDER BY length('F.latitude') DESC ";
			} else {
				//$sql .= " group by bldg_abbre, floor, room_no, gk_department, department, room_name, latitude, gk_sculpture_name ";
				$sql .= " group by F.bldg_abbre, F.floor,  F.gk_department, F.department,  F.latitude, F.gk_sculpture_name ";
				$sql .= " order by F.bldg_abbre asc, F.room_name asc, F.floor asc, F.new_room_no asc, F.department asc ";
			}

			if ($post['limit'] > '0') {
				$limit = addslashes($post['limit']);
				$sql .= " limit $limit ";
			}

		}

		//echo $sql;
		//die();

		$connection = Yii::$app->getDb();
		$command = $connection->createCommand($sql);

		$result = $command->queryAll();

		$rowCount = count($result);

		if ($result[0]) {

			$out['type'] = 'FeatureCollection';

			$i = 0;

			foreach($result as $key=>$value) {

				foreach($value as $field=>$record) {
					$value[$field] = trim($record);
				}

				if ($rowCount > 10) {
					if (stripos($value['room_name'], 'corr') > 0) {
						continue;
					}
					if (stripos($value['room_name'], 'class') !== false) {
						continue;
					}
					if (stripos($value['room_name'], "men's") !== false) {
						continue;
					}
					if (stripos($value['room_name'], 'ofc') !== false) {
						continue;
					}
				}

				if ($post['webapp'] == 'manage') {

					//if (strlen(trim($value['latitude'])) > '12') {
					//	continue;
					//}

					//if ($value['gk_department'] == '') {
					//	continue;
					//}

					if ($i > 20) {
						continue;
					}
				}

				if (trim($value['floor']) == 'GRND') {
					$value['floor'] = 'Ground';
				}
				if (trim($value['floor']) == 'BSMT') {
					$value['floor'] = 'Basement';
				}

				# filter out points that havn't been geolocated yet
				//if (@$post['recordId'] == '' && @$post['webapp'] != 'manage' && strlen(trim($value['latitude'])) < '13' && $value['gk_department'] == '') {
				//	continue;
				//}

				foreach($value as $key2=>$value2) {
					$value2 = trim($value2);
					$value[$key2] = str_replace("'", '', $value2);
					$value2 = mb_convert_encoding($value2, 'UTF-8', 'UTF-8');
					$value[$key2] = str_replace("\\\\", "\\", $value[$key2]);
				}

				//$value['floor'] = preg_replace('/[^0-9,.]/', '', trim($value['floor']));

				if (trim($value['floor']) == '') {
					$value['floor'] = '1st';
				}

				if (trim($value['bldg_name']) == 'HIGGINS') {
					$value['bldg_name'] = 'Higgins Hall';
				}

				//$value['bldg_name']	= ucwords(strtolower(trim($value['bldg_name'])));

				// 	if ($value['room_name']	!= 'Restroom - All-Gender') {
				// 		$value['room_name']	= ucwords(strtolower(trim($value['room_name'])));
				// 	}

				if (stripos('_'.$value['room_name'], 'stair') > '0') {
					$value['room_name']	= ucwords(strtolower(trim($value['room_name'])));
				}

				if (stripos('_'.$value['room_name'], 'COOR') > '0') {
					$value['room_name']	= ucwords(strtolower(trim($value['room_name'])));
				}

				$value['floor']		= strtolower(trim($value['floor']));

				if (trim($value['gk_bldg_id']) == '0001') {
					$value['bldg_name'] = 'ISC';
				}

				if (trim($value['gk_bldg_id']) == '0021') {
					$value['bldg_name'] = 'ARC';
				}

				$out['features'][$i]['type'] = 'Feature';

				$out['features'][$i]['properties']['mapLabelId']	= '';

				$out['features'][$i]['properties']['buildingId']	= trim($value['gk_bldg_id']);

				$out['features'][$i]['properties']['floorId']		= trim($value['gk_floor_id']);
				$out['features'][$i]['properties']['LEVEL_ID']		= trim($value['gk_floor_id']);

				//$out['features'][$i]['properties']['label']				= trim($value['room_name']) . ' ' . $value['id'];
				$out['features'][$i]['properties']['label']				= trim($value['room_name']);

				//	$out['features'][$i]['properties']['category']			= trim($value['gk_category'])=='' ? 'Label' : trim($value['gk_category']);
				$out['features'][$i]['properties']['category']			= 'Information';

				$out['features'][$i]['properties']['fontSize']			= trim($value['gk_fontsize'])<'2' ? intval(24) : intval(trim($value['gk_fontsize']));
				//$out['features'][$i]['properties']['showOnCreation']	= trim($value['gk_showoncreation'])=='' ? true : trim($value['gk_showoncreation']);
				$out['features'][$i]['properties']['showOnCreation']	= true;
				//$out['features'][$i]['properties']['showToolTip']		= trim($value['gk_showtooltip'])=='' ? true : trim($value['gk_showtooltip']);
				//$out['features'][$i]['properties']['showToolTip']		= false;
				//$out['features'][$i]['properties']['tooltipTitle']		= trim($value['gk_tooltiptitle'])=='' ? 'tt title' : trim($value['gk_tooltiptitle']);
				//$out['features'][$i]['properties']['tooltipBody']		= trim($value['gk_tooltipbody'])=='' ? 'tt body' : trim($value['gk_tooltipbody']);

				$out['features'][$i]['properties']['showToolTip']		= false;

				if (trim($value['room_name']) != '') {
					$out['features'][$i]['properties']['tooltipTitle'] = trim($value['room_name']);
				}

				if (trim($value['floor']) != '') {
					$out['features'][$i]['properties']['tooltipBody'] = trim($value['floor']).' floor';

					if (trim($value['new_room_no']) != '') {
						$roomno = trim($value['new_room_no'],'-');
					} else {
						$roomno = trim($value['room_no'],'-');
					}

					if ($roomno != '') {
						$out['features'][$i]['properties']['tooltipBody'] .= '<br>Room: '.$roomno;
					}

				}

				$out['features'][$i]['properties']['location']			= 'URL';

				//$out['features'][$i]['properties']['type']			= trim($value['gk_type'])=='' ? 'IconWithText' : trim($value['gk_type']);
				$out['features'][$i]['properties']['type']				= 'IconWithText';
				//$out['features'][$i]['properties']['type']				= 'Icon';

				$out['features'][$i]['ignoreCollision']					= false;

				//$host = addslashes($post['host']).'/';

				$out['features'][$i]['properties']['partialPath']		= 'images/icons/ic_admin_info_v2.png';

				if (stripos('_'.$value['gk_space_provisions'], 'defibrillator') == '1') {
					$out['features'][$i]['properties']['partialPath'] = 'images/icons/ic_admin_aed.png';
				}

				if (stripos('_'.$value['gk_space_provisions'], 'AED') == '1') {
					$out['features'][$i]['properties']['partialPath'] = 'images/icons/ic_admin_aed.png';
				}

				if (trim($value['room_name']) == 'Sculpture') {
					//$out['features'][$i]['properties']['partialPath'] = 'css/icons/ic_admin_camera.png';
					$out['features'][$i]['properties']['partialPath'] = 'images/icons/ic_artwork.png';
				}

				if (trim($value['accessible']) == 'Y') {
					//$out['features'][$i]['properties']['partialPath'] = 'css/icons/ic_admin_camera.png';
					$out['features'][$i]['properties']['partialPath'] = 'images/icons/accessible.png';
				}

				if (trim($value['room_name']) == 'Restroom - Men') {
					$out['features'][$i]['properties']['partialPath'] = 'images/icons/ic_admin_restroom_mens.png';
				}

				if (trim($value['room_name']) == 'Restroom - Women') {
					$out['features'][$i]['properties']['partialPath'] = 'images/icons/ic_admin_restroom_womens.png';
				}

				if (trim($value['room_name']) == 'Restroom - All-Gender') {
					$out['features'][$i]['properties']['partialPath'] = 'images/icons/ic_admin_restroom_all.png';
				}

				if (stripos('_'.$value['room_name'], 'elev') > '0') {
					$out['features'][$i]['properties']['partialPath'] = 'images/icons/ic_admin_elevator_v2.png';
				}

				if (stripos('_'.$value['room_name'], 'stair') > '0') {
					$out['features'][$i]['properties']['partialPath'] = 'images/icons/ic_admin_stairs.png';
				}

				if (stripos('_'.$value['room_name'], 'gallery') > '0') {
					$out['features'][$i]['properties']['partialPath'] = 'images/icons/ic_artwork.png';
				}

				if (stripos('_'.$value['room_name'], 'fountain') > '0') {
					$out['features'][$i]['properties']['partialPath'] = 'images/icons/ic_admin_water.png';
				}

				$out['features'][$i]['geometry']['type']	= 'Point';

				// 	if (trim($value['latitude']) != '') {
				// 		$value['latitude'] = floatval(substr(trim($value['latitude']),0,9) . rand(10000, 99999));
				// 	} else {
				// 		$value['latitude'] = floatval('-73.96' . rand(1000, 9999));
				// 	}
				//
				// 	if (trim($value['longitude']) != '') {
				// 		$value['longitude'] = floatval(substr(trim($value['longitude']),0,9) . rand(10000, 99999));
				// 	} else {
				// 		$value['longitude'] = floatval('40.69' . rand(1000, 9999));
				// 	}

				$value['longitude']	= substr(trim($value['longitude']),0,10);
				$value['latitude']	= substr(trim($value['latitude']),0,10);

				$out['features'][$i]['geometry']['coordinates'][0]		= trim($value['longitude'])=='' ? '-73.964854' : trim($value['longitude']);
				$out['features'][$i]['geometry']['coordinates'][1]		= trim($value['latitude'])=='' ? '40.690357' : trim($value['latitude']);

				//if (trim($value['new_room_no']) == '') {
				//	$value['new_room_no'] = '-';
				//}

				if (trim($value['gk_display']) == '') {
					$value['gk_display'] = '-';
				}

				$out['features'][$i]['user_properties']['ambiarcId']		= '';
				$out['features'][$i]['user_properties']['recordType']		= 'n';
				$out['features'][$i]['user_properties']['recordId']			= trim($value['id']);
				$out['features'][$i]['user_properties']['accessible']		= trim($value['accessible']);
				$out['features'][$i]['user_properties']['bldgName']			= trim($value['bldg_name']);
				$out['features'][$i]['user_properties']['bldgAbbr']			= trim($value['bldg_abbre']);
				$out['features'][$i]['user_properties']['floorNo']			= trim($value['floor']);
				$out['features'][$i]['user_properties']['roomNo']			= trim($value['room_no'])==''?'1':trim($value['room_no']);
				$out['features'][$i]['user_properties']['newRoomNo']		= trim($value['new_room_no']);
				$out['features'][$i]['user_properties']['gkDisplay']		= trim($value['gk_display']);
				$out['features'][$i]['user_properties']['legendCopy']		= trim($value['copy']);

				if (trim($value['gk_department']) != '') {
					$out['features'][$i]['user_properties']['gkDepartment']		= trim($value['gk_department']);
					//$out['features'][$i]['properties']['label']				= trim($value['gk_department']);
				}

				if (trim($value['gk_sculpture_name']) != '') {
					//$out['features'][$i]['user_properties']['gkArtist']		= trim($value['gk_sculpture_artist']);
					$out['features'][$i]['user_properties']['gkArtistFirst']	= trim($value['gk_sculpture_artist_first']);
					$out['features'][$i]['user_properties']['gkArtistLast']		= trim($value['gk_sculpture_artist_last']);
					$out['features'][$i]['user_properties']['gkArtName']		= trim($value['gk_sculpture_name']);
					$out['features'][$i]['user_properties']['gkArtDate']		= trim($value['gk_sculpture_date']);
				}

				if (trim($value['gk_floor_id']) == '0000') {

					$out['features'][$i]['properties']['buildingId']	= '';
					$out['features'][$i]['properties']['LEVEL_ID']		= '';
					$out['features'][$i]['properties']['floorId']		= '';
					$out['features'][$i]['user_properties']['roomNo']	= '';
					//unset($out['features'][$i]['properties']['buildingId']);
					//unset($out['features'][$i]['properties']['floorId']);

				}

				//$out['features'][$i]['user_properties']['count']			= $i . ' ' . $rowCount;
				$out['features'][$i]['user_properties']['itemId']			= $i;
				//$out['features'][$i]['user_properties']['sql']			= $this->trim_all($sql);

				//$out['features'][$i]['user_properties']['params1']	= $posts;
				//$out['features'][$i]['user_properties']['params2']	= $post;
				//$out['features'][$i]['user_properties']['params3']	= $stuff;
				//$out['features'][$i]['user_properties']['params4']	= $_POST;

				$j = $i;

				$i++;
				if ($i > 10) {
				//	break;
				}

			}

			//if ($post['webapp'] != 'manage' && $i < 12) {
			if ($post['webapp'] != 'manage') {

				// 	$out['features'][$i]['type'] = 'Feature';
				//
				// 	$out['features'][$i]['properties']['LEVEL_ID']		= '0001';
				// 	$out['features'][$i]['properties']['category']		= 'Information';
				// 	$out['features'][$i]['properties']['floorId']		= '0001';
				// 	$out['features'][$i]['properties']['buildingId']	= '0001';
				// 	$out['features'][$i]['properties']['fontSize']		= 24;
				// 	$out['features'][$i]['properties']['location']		= 'URL';
				// 	$out['features'][$i]['properties']['partialPath']	= 'images/icons/ic_admin_info_v2.png';
				// 	$out['features'][$i]['properties']['showToolTip']	= false;
				// 	$out['features'][$i]['properties']['tooltipBody']	= '';
				// 	$out['features'][$i]['properties']['tooltipTitle']	= '';
				// 	$out['features'][$i]['properties']['label']			= 'ignore';
				// 	$out['features'][$i]['properties']['type']			= 'Icon';
				//
				// 	$out['features'][$i]['geometry']['type']			= 'Point';
				// 	$out['features'][$i]['geometry']['coordinates'][0]	= '-73.964854';
				// 	$out['features'][$i]['geometry']['coordinates'][1]	= '40.690357';
				//
				// 	$out['features'][$i]['user_properties']['accessible']	= 'N';
				// 	$out['features'][$i]['user_properties']['bldgAbbr']		= '---';
				// 	$out['features'][$i]['user_properties']['bldgName']		= '---';
				// 	$out['features'][$i]['user_properties']['floorNo']		= '---';
				// 	$out['features'][$i]['user_properties']['gkDisplay']	= '---';
				// 	$out['features'][$i]['user_properties']['newRoomNo']	= '---';
				// 	$out['features'][$i]['user_properties']['roomNo']	= '---';

				$pad = 3;
				if ($post['webapp'] != '') {
					$pad = $pad + addslashes($post['countzeros']);
				}

				for ($x = 0; $x <= $pad; $x++) {

					$out['features'][$i] = $out['features'][$j];

					//$out['features'][$i]['properties']['label']				= uniqid();
					$out['features'][$i]['properties']['showOnCreation']	= false;
					$out['features'][$i]['user_properties']['recordType']	= 'x';
					$out['features'][$i]['user_properties']['itemId']		= $i;
					$out['features'][$i]['user_properties']['recordId']		= '1';
					$out['features'][$i]['user_properties']['pad']			= $pad;
					//$out['features'][$i]['user_properties']['sql']			= $this->trim_all($sql);

					//$value['longitude'] = floatval(substr(trim($value['longitude']),0,14) . rand(10000, 99999));
					//$value['latitude'] = floatval(substr(trim($value['latitude']),0,14) . rand(10000, 99999));

					$out['features'][$i]['geometry']['coordinates'][0]	= $value['longitude'];
					$out['features'][$i]['geometry']['coordinates'][1]	= $value['latitude'];

					$j++;
					$i++;

				}

			}

		} else {

			$i = 1;

			$out['features'][$i]['type'] = 'Feature';
			$out['features'][$i]['properties']['POINT_ID']		= $i;
			$out['features'][$i]['properties']['CATEGORY']		= $i;
			$out['features'][$i]['properties']['floorId']		= '0001';
			$out['features'][$i]['properties']['buildingId']	= '0001';
			$out['features'][$i]['properties']['label']			= 'ignore';
			$out['features'][$i]['properties']['type']			= 'Icon';
			$out['features'][$i]['geometry']['type']			= 'Point';
			$out['features'][$i]['geometry']['coordinates'][0]	= '-73.964854';
			$out['features'][$i]['geometry']['coordinates'][1]	= '40.690357';
			$out['features'][$i]['user_properties']['itemId']	= $i;
			$out['features'][$i]['user_properties']['recordId']	= '1';
			//$out['features'][$i]['user_properties']['sql']		= $this->trim_all($sql);

			//$out['success'] = false;
			//$out['message'] = 'no matches';
			//$out['sql'] = $this->trim_all($sql);
		}

		header('Content-Type: application/json');
		echo json_encode($out);
		die();
    }

	public function actionUnitytest() {

		header('Content-Type: application/json');

		//$_REQUEST['line'] = __LINE__;
		//$_REQUEST['time'] = time();
		//echo json_encode($_REQUEST);

		//$_POST['timx'] = time();
		echo json_encode($_POST);
		die();

		echo '{ "title" : "Decode JSON", "ID" : 20, "buttons" :
			[
				{
					"title" : "Red ",
					"image" : "Image Url"
				},
				{
					"title" : "Green ",
					"image" : "Image Url"
				},
				{
					"title" : "Blue ",
					"image" : "Image Url"
				},
				{
					"title" : "Yellow ",
					"image" : "Image Url"
				}
			]
		}';
		die();

	}

    private function trim_all( $str , $what = NULL , $with = ' ' ) {
		if( $what === NULL ) {
			//  Character      Decimal      Use
			//  "\0"            0           Null Character
			//  "\t"            9           Tab
			//  "\n"           10           New line
			//  "\x0B"         11           Vertical Tab
			//  "\r"           13           New Line in Mac
			//  " "            32           Space

			$what   = "\\x00-\\x20";    //all white-spaces and control chars
		}

		return trim( preg_replace( "/[".$what."]+/" , $with , $str ) , $what );
	}

    public function actionToggle() {

    	$sql = " UPDATE facilities SET gk_display = '".addslashes($_POST['display'])."' WHERE id = '".addslashes($_POST['id'])."' ";

		try {
		    $connection = Yii::$app->getDb();
			$command = $connection->createCommand($sql);
			$result = $command->query();
			$arr['success'] = true;
			$arr['message'] = '';
		} catch (\yii\db\Exception $e) {
			$arr['success'] = true;
			$arr['message'] = $e;
		}

		header('Content-Type: application/json');
		echo json_encode($arr);
		die();

    }

    public function actionLookup() {

    	die();

    	if (@$_GET['field']=='') {
    		$out[] = 'no match';
    		return Json::encode($out);
			die();
    	}

		$connection = Yii::$app->getDb();
		$command = $connection->createCommand("
			SELECT DISTINCT(".$_GET['field'].")
			FROM facilities
			ORDER BY ".$_GET['field']." ASC ");

		$result = $command->queryAll();

		if ($result[0]) {
			foreach($result as $key=>$value) {
				$rec = trim($value[$_GET['field']]);
				if ($rec > '0') {
					//$rec = preg_replace('/[^A-Za-z0-9\. -]/', '', $rec);
					$out[] = $rec;
				}
			}
		} else {
			$out[] = 'no match';
		}

		return Json::encode($out);
		die();
    }

    /**
     * Lists all Facilities models.
     * @return mixed
     */
    public function actionIndex()
    {
    	die();

        $searchModel = new FacilitiesSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Facilities model.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionView($id)
    {
    	die();

        return $this->render('view', [
            'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new Facilities model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate()
    {
    	die();

        $model = new Facilities();

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('create', [
            'model' => $model,
        ]);
    }

    /**
     * Updates an existing Facilities model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionUpdate($id)
    {
    	die();

        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
			Yii::$app->session->setFlash('success', "Success, the record has been updated.");
            return $this->redirect(['update', 'id' => $model->id]);
        }

        return $this->render('update', [
            'model' => $model,
        ]);
    }

    /**
     * Deletes an existing Facilities model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function actionDelete($id)
    {
    	die();

        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Finds the Facilities model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Facilities the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Facilities::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
