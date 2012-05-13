<?php
require_once ('ini.php');

require_once('class/chart/graphicalchart.class.php');
require_once('class/chart/realchart.class.php');
require_once('class/chart/realprice.class.php');
require_once('class/chart/candlestick.class.php');
require_once('class/chart/dot.class.php');


require_once('test/test.php');
$aDataPrices = _getTestData();

$iMargin = 20;
$oGraphicalChart = new graphicalChart(500,350, $iMargin);

$iMinutesPerPrice = 30;
$Zoom = 1;
$aIndicators = array('ma'   => array(10,20)
                    ,'bol'  => array('n'=>10,'std_dev'=>2)
                    ,'rsi'  => array(4));
$oRealChart = new realChart($iMinutesPerPrice, $Zoom, $aIndicators);
foreach($aDataPrices as $aDataPrice){
    $oRealChart->addPrice($aDataPrice['datetime'],new candlestick($aDataPrice['datetime']
                                                                ,$aDataPrice['min']
                                                                ,$aDataPrice['max']
                                                                ,$aDataPrice['open']
                                                                ,$aDataPrice['close']
                                                                ,$aDataPrice['volume']));
}
$oGraphicalChart->buildGraphicalChart($oRealChart);

$oGraphicalChart->draw();


?>
