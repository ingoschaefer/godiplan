<?php

/*

Plugin Name: Gottesdienstplan

Description: Bietet die Möglichkeit, einen Gottesdienstplan aus den Veranstaltungen als CSV zu exportieren.

Version: 0.3.0

Author: Ingo Schaefer

Author URI: http://www.ingo-schaefer.de

Text Domain: godiplan

*/


/**
 * Create a submenu item within the Events Manager menu. 
 * In Multisite Global Mode, the admin menu will only appear on the main blog, this can be changed by modifying the first line of code in this function.
 */
function my_em_godiplan_submenu () {
	$ms_global_mode = !EM_MS_GLOBAL || is_main_site();
	if(function_exists('add_submenu_page') && ($ms_global_mode) ) {
   		$plugin_page = add_submenu_page('edit.php?post_type='.EM_POST_TYPE_EVENT, __( 'Gottesdienstplan', 'godiplan' ), __( 'Gottesdienstplan', 'godiplan' ), 'edit_events', 'godiplan-start', 'godiplan_start_contents');
  	}
}
add_action('admin_menu','my_em_godiplan_submenu', 20);

function isKirchenjahrPluginThere() {
	$found=null;
	if (class_exists('evkj_WidgetAPI')) {
		$found=false;
		$method = new ReflectionMethod('evkj_WidgetAPI', 'getday');
    		foreach($method->getParameters() as $parameter) {
			if ($parameter->name=='rawarray') {
				$found=true;
				break;
			}
		}
	}
	return $found;
}

function godiplan_start_contents() {

?>

<h1>

<?php 
if (class_exists('EM_Events')) {
	?>
	<h2> <?php esc_html_e( 'Hier kann man einen Export der Veranstaltungen als CSV (für Excel) anklicken', 'godiplan' ); ?></h2>
	<?php
	$kirchenJahrPluginThere=isKirchenjahrPluginThere();
	if(!is_null($kirchenJahrPluginThere)) {
		if( !$kirchenJahrPluginThere) {
			?>
			    <div class="notice notice-warning is-dismissible">
        <p><?php esc_html_e( 'Warnung: Plugin Kirchenjahr evangelisch hat benötigten Parameter nicht, vermutlich falsche Version installiert, Name des Sonntags/Feiertags kann nicht ermittelt werden.','godiplan' ); ?></p>
    </div>
	<?php		
		}
	} else {?>
			    <div class="notice notice-warning is-dismissible">
        <p><?php esc_html_e( 'Warnung: Plugin Kirchenjahr evangelisch nicht korrekt installiert, Name des Sonntags/Feiertags kann nicht ermittelt werden.','godiplan' );?></p>
    </div>
	<?php
	}
?>
</h1>
<div class="godiplan_get_download_form">
<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" id="godiplan_get_download_form" >
<input type="hidden" name="action" value="godiplan_get_download_form">
<?php
	wp_nonce_field('godiplan_get_download_form');
?>
	<h2><?php esc_html_e( 'Veranstaltungskategorien wählen', 'godiplan' ); ?></h2>
<?php
	$event_categories=EM_Categories::get(array('array'=>1,'target'=>'raw'));
	// print_r($event_categories);
?>
				<div><input type="checkbox" name="category[]" value="-1" id="all"><label for="all">Alle Kategorien</label></input></div>
	<h2><?php esc_html_e( 'oder', 'godiplan' ); ?></h2>
				<?php 
	foreach($event_categories as $category) { 
?>
				 <div><input type="checkbox" name="category[]" value="<?php echo $category->term_id; ?>" id="<?php echo $category->term_id; ?>"><label for="<?php echo $category->term_id; ?>"><?php echo $category->name; ?></label></input></div>
				<?php 
	} 
?>
<?php
	if( current_user_can( 'publish_events') ) { 
		?>
<h2><?php esc_html_e( 'Für den Planer', 'godiplan' ); ?></h2>
<div><input type="checkbox" name="private" value="1" id="private"><label for="private">Auch Veranstaltungen im Entwurfsstatus anzeigen</label></input></div>
<?php
	} // if( current_user_can( 'publish_events') )
		?>
<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Submit Form"></p>
	</form>
	<br/><br/>
	<div id="nds_form_feedback"></div>
	<br/><br/>			
	</div>
<?php
} else {
	esc_html_e( 'Plugin Event-Manager nicht installiert!', 'godiplan' );
}

}
?>
<?php

function godiplan_get_download_form() {
	$NL="\r\n";
	$SEP=";";
	$QUOT='"';
	if( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'godiplan_get_download_form') ) {
		$filters=array('array'=>1,'limit'=>10,'orderby'=>'event_start_date','private'=>1);

		if( !current_user_can( 'publish_events') || !isset( $_POST['private'] )) {
			$filters['status']=1;
		}
		$categories = $_POST['category'];
		if ( in_array(-1,$categories)) {
			// no filter
		} else {
			$filters['category']=implode(',',$categories);
		}

		// do the processing
		if (class_exists('EM_Events')) {
			$isKirchenjahrPluginThere=isKirchenjahrPluginThere();
			if($isKirchenjahrPluginThere) {
				$evkj_WidgetAPI=new evkj_WidgetAPI();
			}
			$locations=EM_Locations::get(array('array'=>1,'target'=>'raw'));
			$filename=current_time('Y-m-d') . "_gottesdienstplan.csv";

			header("Expires: 0");
			header("Cache-Control: no-cache, no-store, must-revalidate"); 
			header('Cache-Control: pre-check=0, post-check=0, max-age=0', false); 
			header("Pragma: no-cache");	
			header("Content-Disposition:attachment; filename=$filename");
			header("Content-Type: application/force-download");

			// print_r($locations);
			$orte=array();
			foreach($locations as $location) {
				$orte[$location['location_id']]=$location['location_name'];
			}
			print('Gottesdienstplan' . $NL);
			print('Datum' . $SEP . 'Sonntag' . $SEP . 'Uhrzeit' . $SEP . 'Ort' . $SEP . 'Besonderheiten' . $SEP . 'Pfr/Pr.' . $SEP . 'Musik' . $SEP . 'KiGo' . $SEP .  'GoDiLei' . $SEP . 'KirDienst' . $NL);

//    			print_r(EM_Events::get( $filters ));
			$filters['target']='raw';
    			$events=EM_Events::get( $filters );

   			foreach ( $events as $EM_Event ) {
				$post_id=$EM_Event['post_id'];
        			$post=get_post($post_id,'ARRAY_A');
				$post_meta=get_post_meta($post_id);				
				//print $EM_Event->output();
				print($EM_Event['event_start_date'] . $SEP);
				print($QUOT . getNameOfDate($EM_Event['event_start_date'], $evkj_WidgetAPI) . $QUOT . $SEP);
				print($EM_Event['event_start_time'] . $SEP);
				$location_id=$EM_Event['location_id'];
				print($QUOT . $orte[$location_id] . $QUOT . $SEP);
				$besonderheiten='';
				if( $post_meta['Abendmahl'][0]=='ja' ) {
					$besonderheiten=$besonderheiten . 'Abendmahl' . ',';
				}
				if( $post_meta['Taufe'][0]=='ja' ) {
					$besonderheiten=$besonderheiten . 'Taufe' . ',';
				}
				if( isset( $post_meta['Besonderheiten'][0])) {
					$besonderheiten=$besonderheiten . $post_meta['Besonderheiten'][0] . ',';
				}
        			print($QUOT . $besonderheiten . $QUOT . $SEP);
				print($QUOT . $post_meta['Pfarrer/Prediger'][0] . $QUOT . $SEP);
				print($QUOT . $post_meta['Musik'][0] . $QUOT . $SEP);
				print($QUOT . $post_meta['Kindergottesdienst'][0] . $QUOT . $SEP);
				print($QUOT . $post_meta['Gottesdienstleiter'][0] . $QUOT . $SEP);
				print($QUOT . $post_meta['Kirchendienst'][0] . $QUOT . $SEP);
				print($NL);
//				print_r($post);
//        			print_r($post_meta);
     			}
   		}
		// add the admin notice
		$admin_notice = "success";

		// redirect the user to the appropriate page
		// $this->custom_redirect( $admin_notice, $_POST );
		exit;
	}			
	else {
		wp_die( __( 'Invalid nonce specified', $this->plugin_name ), __( 'Error', $this->plugin_name ), array(
					'response' 	=> 403,
					'back_link' => 'admin.php?page=' . $this->plugin_name,
			) );
	}
}

function getNameOfDate($event_date, $evkj_WidgetAPI) {
	$isKirchenjahrPluginThere=isKirchenjahrPluginThere();
	if($isKirchenjahrPluginThere) {
		$widgetResult=$evkj_WidgetAPI->getday('small','none','false',$event_date,true,true);
		if($widgetResult['litdate']==$event_date) {
			return $widgetResult['litname'];
		}
	}
	$dayofweek = date('w', strtotime($event_date));
	global $wp_locale;
	return $wp_locale->get_weekday($dayofweek);
}

add_action( 'admin_post_godiplan_get_download_form', 'godiplan_get_download_form');

function register_my_plugin_scripts() {

wp_register_style( 'my-plugin', plugins_url( 'ddd/css/plugin.css' ) );

wp_register_script( 'my-plugin', plugins_url( 'ddd/js/plugin.js' ) );

}



// add_action( 'admin_enqueue_scripts', 'register_my_plugin_scripts' );



function load_my_plugin_scripts( $hook ) {

// Load only on ?page=sample-page

if( $hook != 'toplevel_page_sample-page' ) {

return;

}

// Load style & scripts.

wp_enqueue_style( 'my-plugin' );

wp_enqueue_script( 'my-plugin' );

}



// add_action( 'admin_enqueue_scripts', 'load_my_plugin_scripts' );
