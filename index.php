<?php

//require('class/update_telegraph.class.php');


require_once ('ini.php');

$oPage = new StdClass();

require_once('visual/visual.update.php');
require_once('visual/visual.display_TPO.php');

if (count($_POST)>0){
    if (array_key_exists('update_dukascopy', $_POST)){
        require_once('class/update_dukascopy.class.php');
    }
    if (array_key_exists('update_telegraph', $_POST)){
        require_once('class/update_telegraph.class.php');
    }
    
    if (array_key_exists('display_day_frame_tpo_dukascopy', $_POST) || array_key_exists('display_day_frame_tpo_telegraph', $_POST)){
        require_once('class/display_TPO.class.php');
    }
}

$oSmarty->assign('oPage',$oPage);
echo $oSmarty->fetch('index.tpl');


 ?>