<?php
require_once('class/chart/realchart.class.php');

class data_analysis {
    private $_iCash;
    private $_aPrices;
    
    public function data_analysis($aPrices){
        $this->_aPrices = $aPrices;
        $this->_aStats = array('sequence'   => array('letters'  => ''
                                                    ,'trades'   => array())
                              ,'total'      => array('gains'   => 0
                                                    ,'loss'    => 0
                                                    ,'profit'  => 0)
                              ,'max'        => array( 'gains'            => 0
                                                    ,'loss'             => 0
                                                    ,'gains_in_a_row'   => 0
                                                    ,'losses_in_a_row'  => 0)
                              ,'avg'        => array( 'gains'            => 0
                                                    ,'loss'             => 0
                                                    ,'failures'         => 0
                                                    ,'comp_gains'       => 0
                                                    ,'comp_losses'      => 0
                                                    ,'gains_in_a_row'   => 0
                                                    ,'losses_in_a_row'  => 0));
    }
    
    public function run($sStrategy){
        foreach($this->_aPrices as $oRealPrice){
            $oRealPrice->clearTrades();
        }
        $sMethod = '_'.$sStrategy;
        $this->$sMethod();
        $this->_buildStats();
    }
    
    public function getStats(){
        return $this->_aStats;
    }
    
    private function _buildStats() {
        $aCurrentTrade = array('dir' => NULL, 'price' => NULL);
        $iConsecutive = 0;
        $aCash = array('trade_pct' => 0, 'trade_base'=> 100, 'next_trade_base' => 100);
        foreach($this->_aPrices as $sDateTime=>$oRealPrice){
            $aTrades = $oRealPrice->getTrade();
            if (!empty($aTrades)){
                foreach($aTrades as $aTrade){
                    $iTrade = $aTrade['dir'];
                    
                    if (is_null($iTrade)){
                        //Do nothing
                    }
                    elseif (($iTrade==realPrice::TRADE_SELL) || ($iTrade==realPrice::TRADE_BUY)){
                        $aCurrentTrade = $aTrade;
                        $aCash['trade_pct'] += $aTrade['pct'];
                    }
                    elseif ($iTrade===realPrice::TRADE_CLOSE){
                        $this->_aStats['sequence']['trades'][] = array('datetime'=> $sDateTime
                                                                      ,'dir'    => $aCurrentTrade['dir']
                                                                      ,'open'   => $aCurrentTrade['price']
                                                                      ,'close'  => $aTrade['price']);
                        $bTradeWon = (($aCurrentTrade['dir']==realPrice::TRADE_BUY) && ($aTrade['price'] > $aCurrentTrade['price']))
                                    || (($aCurrentTrade['dir']==realPrice::TRADE_SELL) && ($aTrade['price'] < $aCurrentTrade['price']));
                        
                        if ($aTrade['pct']<$aCash['trade_pct']){
                            $nTradePct = $aTrade['pct'];
                            $aCash['trade_pct'] -= $nTradePct;
                        }
                        else {
                            $nTradePct = $aCash['trade_pct'];
                            $aCash['trade_pct'] = 0;
                        }
                        
                        if ($bTradeWon) {
                            $aCash['next_trade_base'] += ($aCash['trade_base']*(abs($aTrade['price']-$aCurrentTrade['price'])/$aCurrentTrade['price']))*($nTradePct/100);
                        }
                        else {
                            $aCash['next_trade_base'] -= ($aCash['trade_base']*(abs($aTrade['price']-$aCurrentTrade['price'])/$aCurrentTrade['price']))*($nTradePct/100);
                        }
                        
                        if ($aCash['trade_pct'] == 0) {
                            $aCash['trade_base'] = $aCash['next_trade_base'];
                        }
                            
                        
                        $this->_aStats['sequence']['letters'] .= $bTradeWon ? 'G' : 'L';
                        if ($bTradeWon && ($iConsecutive>0)){       // won
                            $iConsecutive++;
                            $this->_aStats['max']['gains_in_a_row'] = max($this->_aStats['max']['gains_in_a_row'],$iConsecutive);
                        }
                        elseif (!$bTradeWon && ($iConsecutive<0)){   //loss
                            $iConsecutive--;
                            $this->_aStats['max']['losses_in_a_row'] = max($this->_aStats['max']['losses_in_a_row'],abs($iConsecutive));
                        }
                        else {
                            $iConsecutive = $bTradeWon ? 1:-1;
                            if ($bTradeWon){
                                $this->_aStats['max']['gains_in_a_row'] = max($this->_aStats['max']['gains_in_a_row'],$iConsecutive);
                            }
                            else {
                                $this->_aStats['max']['losses_in_a_row'] = max($this->_aStats['max']['losses_in_a_row'],abs($iConsecutive));
                            }
                        }

                        $nDifference = abs($aTrade['price'] - $aCurrentTrade['price']);
                        if ($bTradeWon){
                            $this->_aStats['total']['gains'] += $nDifference;
                            $this->_aStats['max']['gains'] = max($this->_aStats['max']['gains'],$nDifference);
                        }
                        else {
                            $this->_aStats['total']['loss'] += $nDifference;
                            $this->_aStats['max']['loss'] = max($this->_aStats['max']['loss'],$nDifference);
                        }

                        $aCurrentTrade = array('dir'   => NULL
                                    , 'price' => NULL);
                    }
                }
            }
            else {
                $iTrade = NULL;
            }
        }
        
        $this->_aStats['total']['cash_pct'] = $aCash['trade_base'];
        $this->_aStats['total']['profit'] = $this->_aStats['total']['gains']-$this->_aStats['total']['loss'];
        
        $iTimesWon = substr_count($this->_aStats['sequence']['letters'], 'G');
        $iTimesLost = substr_count($this->_aStats['sequence']['letters'], 'L');
        if ($iTimesWon>0){
            $this->_aStats['avg']['gains'] = $this->_aStats['total']['gains']/$iTimesWon;
        }
        if ($iTimesLost>0){
            $this->_aStats['avg']['loss'] = $this->_aStats['total']['loss']/$iTimesLost;
        }
        
        if (strlen($this->_aStats['sequence']['letters'])>0){
            $this->_aStats['avg']['failures'] = $iTimesLost/($iTimesLost+$iTimesWon);
        }
        else {
            $this->_aStats['avg']['failures'] = 0;
        }
        
        $this->_aStats['avg']['ratio_gains_losses'] = 0;
        
        if ($this->_aStats['avg']['failures']>0){
            $this->_aStats['avg']['comp_gains'] = $this->_aStats['avg']['gains']*(1-$this->_aStats['avg']['failures']);
            $this->_aStats['avg']['comp_losses'] = $this->_aStats['avg']['loss']*$this->_aStats['avg']['failures'];
            
            if ($this->_aStats['avg']['comp_losses']>0){
                $this->_aStats['avg']['ratio_gains_losses'] = $this->_aStats['avg']['comp_gains']/$this->_aStats['avg']['comp_losses'];
            }
        }
        
        $aDiffLoss = array_diff(explode(' ', str_replace('G',' ',$this->_aStats['sequence']['letters'])), array(''));
        $sDiffLoss = implode('',$aDiffLoss);
        if (count($aDiffLoss)>0){
            $this->_aStats['avg']['losses_in_a_row'] = strlen($sDiffLoss)/count($aDiffLoss);
        }
        else {
            $this->_aStats['avg']['losses_in_a_row'] = 0;
        }
        
        $aDiffGain = array_diff(explode(' ', str_replace('L',' ',$this->_aStats['sequence']['letters'])), array(''));
        $sDiffGain = implode('',$aDiffGain);
        if (count($aDiffGain)>0){
            $this->_aStats['avg']['gains_in_a_row'] = strlen($sDiffGain)/count($aDiffGain);
        }
        else {
            $this->_aStats['avg']['gains_in_a_row'] = 0;
        }
        
    }
    
    /*
     * 1. Continue the trend once the price goes farther bollinger band
     * 2. Close when bar completely crosses MA20 or touches the opposite bollinger band
     * 
     *  FAILURE: 75%
     */
    private function _strategy1(){
        $oPreviousRealPrice = NULL;
        $iTrading = realPrice::TRADE_CLOSE;
        foreach($this->_aPrices as $iDateTime=>$oRealPrice){
            $aIndicatorsData = $oRealPrice->getIndicators()->getData();
            if (!is_null($aIndicatorsData['BOL']['real']) && !is_null($oPreviousRealPrice)){
                if ($iTrading==realPrice::TRADE_CLOSE){
                    if (($oRealPrice->getMax()>$aIndicatorsData['BOL']['real']['up']) && ($oRealPrice->getClose()>$oPreviousRealPrice->getClose())){
                        $oRealPrice->addTrade(realPrice::TRADE_BUY,$oRealPrice->getClose());
                        $iTrading=realPrice::TRADE_BUY;
                    }
                    
                    if (($oRealPrice->getMin()<$aIndicatorsData['BOL']['real']['down']) && ($oRealPrice->getClose()<$oPreviousRealPrice->getClose())){
                        $oRealPrice->addTrade(realPrice::TRADE_SELL,$oRealPrice->getClose());
                        $iTrading=realPrice::TRADE_SELL;
                    }
                }
                
                if ($iTrading!=realPrice::TRADE_CLOSE){
                    if (($iTrading==realPrice::TRADE_BUY) && (($oRealPrice->getMax()<$aIndicatorsData['MA'][20]['real']) || ($oRealPrice->getMin()<$aIndicatorsData['BOL']['real']['down']))){
                        $iTrading=realPrice::TRADE_CLOSE;
                        $oRealPrice->addTrade(realPrice::TRADE_CLOSE,$oRealPrice->getClose());
                    }
                    if (($iTrading==realPrice::TRADE_SELL) && (($oRealPrice->getMin()>$aIndicatorsData['MA'][20]['real']) || ($oRealPrice->getMax()>$aIndicatorsData['BOL']['real']['up']))){
                        $iTrading=realPrice::TRADE_CLOSE;
                        $oRealPrice->addTrade(realPrice::TRADE_CLOSE,$oRealPrice->getClose());
                    }
                }
            }
            
            $oPreviousRealPrice = $oRealPrice;
        }
    }
    
    
    /**
     * 1. Open opposite when touches bollinger band and length of the trail is bigger than length of the body
     * 2. Close when touches the opposite bollinger band, price surpass limit price at the opening bar (losing money) or price goes below/above previous bar
     * 
     * FAILURE 52%
     */
    private function _strategy2(){
        $oPreviousRealPrice = NULL;
        $iTrading = realPrice::TRADE_CLOSE;
        $nLimitPrice = NULL;
        foreach($this->_aPrices as $iDateTime=>$oRealPrice){
            $aIndicatorsData = $oRealPrice->getIndicators()->getData();
            if (!is_null($aIndicatorsData['BOL']['real']) && !is_null($oPreviousRealPrice)){
                $bGreenBar = ($oRealPrice->getClose()>$oRealPrice->getOpen());
                
                $nTrail = ($bGreenBar) ? $oRealPrice->getMax()-$oRealPrice->getClose():$oRealPrice->getClose()-$oRealPrice->getMin();
                $nBody = abs($oRealPrice->getOpen()-$oRealPrice->getClose());
                $bBollingerCrossed = ($oRealPrice->getMin()<$aIndicatorsData['BOL']['real']['down']) || ($oRealPrice->getMax()>$aIndicatorsData['BOL']['real']['up']);
                
                if (($iTrading!=realPrice::TRADE_CLOSE) && ($nBody>$nTrail)){
                    if (($iTrading==realPrice::TRADE_BUY) && 
                            (($oRealPrice->getMin()<$nLimitPrice) // Below limit and Small trail - LOSS
                            || ($oRealPrice->getMax()>$aIndicatorsData['BOL']['real']['up']) // Close when touches the opposite bollinger
                            || ($oPreviousRealPrice->getMin()>$oRealPrice->getMin()))){ // Close when min goes below previous min
                        $iTrading=realPrice::TRADE_CLOSE;
                        $oRealPrice->addTrade(realPrice::TRADE_CLOSE,$oRealPrice->getClose());
                    }
                    if (($iTrading==realPrice::TRADE_SELL) && 
                            (($oRealPrice->getMax()>$nLimitPrice) 
                            || ($oRealPrice->getMin()<$aIndicatorsData['BOL']['real']['down'])
                            || ($oPreviousRealPrice->getMax()<$oRealPrice->getMax()))){
                        $iTrading=realPrice::TRADE_CLOSE;
                        $oRealPrice->addTrade(realPrice::TRADE_CLOSE,$oRealPrice->getClose());
                    }
                }
                    
                if (($iTrading==realPrice::TRADE_CLOSE) && ($nBody<$nTrail) && $bBollingerCrossed){
                    if ($oRealPrice->getMax()>$aIndicatorsData['BOL']['real']['up']){
                        $iTrading=realPrice::TRADE_SELL;
                        $nLimitPrice = $oRealPrice->getMax();
                        $oRealPrice->addTrade(realPrice::TRADE_SELL,$oRealPrice->getClose());
                    }
                    elseif ($oRealPrice->getMin()<$aIndicatorsData['BOL']['real']['down']){
                        $iTrading=realPrice::TRADE_BUY;
                        $nLimitPrice = $oRealPrice->getMin();
                        $oRealPrice->addTrade(realPrice::TRADE_BUY,$oRealPrice->getClose());
                    }
                }
            }
            
            $oPreviousRealPrice = $oRealPrice;
        }
    }
    
    /**
     * Follow parabolic SAR 
     * 
     * Failures 59%
     */
    private function _strategy3(){
        $oPreviousRealPrice = NULL;
        $aPreviousIndicatorsData = NULL;
        $bTrading = false;
        $iTrading = realPrice::TRADE_CLOSE;

        $aTrade = array('dir' => NULL, 'price'=>NULL);
        foreach($this->_aPrices as $iDateTime=>$oRealPrice){
            $aIndicatorsData = $oRealPrice->getIndicators()->getData();
            
            if (!is_null($aIndicatorsData['SAR']) && !is_null($oPreviousRealPrice) && !is_null($aPreviousIndicatorsData) && !is_null($aPreviousIndicatorsData['SAR'])){
                $bCurrentTrendDown = ($aIndicatorsData['SAR']['real']['trend']=='down');
                $bPreviousTrendDown = ($aPreviousIndicatorsData['SAR']['real']['trend']=='down');
                if ($bCurrentTrendDown ^ $bPreviousTrendDown){
                    $bOutOfPrice = (($aPreviousIndicatorsData['SAR']['real']['psar']>$oRealPrice->getMax())
                                     || ($aPreviousIndicatorsData['SAR']['real']['psar']<$oRealPrice->getMin()));
                            
                    if ($bOutOfPrice) {
                        $nTradePrice = $oRealPrice->getClose();
                    }
                    else {
                        $nTradePrice = $aPreviousIndicatorsData['SAR']['real']['psar'];
                    }
                    
                    if ($bTrading) {
                        $oRealPrice->addTrade(realPrice::TRADE_CLOSE, $nTradePrice);
                    }
                    if ($bCurrentTrendDown){
                        $oRealPrice->addTrade(realPrice::TRADE_SELL, $nTradePrice);
                    }
                    else {
                        $oRealPrice->addTrade(realPrice::TRADE_BUY, $nTradePrice);
                    }
                    $bTrading = true;
                }
                else {
                    //Do nothing, no trend change
                }
            }
            $oPreviousRealPrice = $oRealPrice;
            $aPreviousIndicatorsData = $oPreviousRealPrice->getIndicators()->getData();
        }
    }
    
    /**
     * Open when MA10 and MA20 goes the same direction, and the same direction of the opening price
     * Close when price touches MA20 again
     */
    private function _strategy4(){
        $oPreviousRealPrice = NULL;
        $aPreviousPriceIndicatorsData = NULL;
        
        $iPreviousDay = NULL;
        
        $nPriceAtStartOfDay = NULL;
        $nPreviousDayClosePrice = NULL;
        $iTrading = NULL;
        foreach($this->_aPrices as $sDateTime=>$oRealPrice){
            $iCurrentDay=substr($sDateTime,11,2);
            $aIndicatorsData = $oRealPrice->getIndicators()->getData();
            
            if (!is_null($iPreviousDay)){
                if ($iPreviousDay!=$iCurrentDay){
                    $nPriceAtStartOfDay = $oRealPrice->getOpen();
                    $nPreviousDayClosePrice = $oPreviousRealPrice->getClose();
                }
                if (!is_null($aPreviousPriceIndicatorsData) 
                        && !is_null($nPriceAtStartOfDay)
                        && !is_null($aIndicatorsData['MA'][20]['real'])
                        && !is_null($aPreviousPriceIndicatorsData['MA'][20]['real'])){
                    
                    if(is_null($iTrading)){
                        if (($aIndicatorsData['MA'][20]['real']>$aPreviousPriceIndicatorsData['MA'][20]['real'])
                        && ($aIndicatorsData['MA'][10]['real']>$aPreviousPriceIndicatorsData['MA'][10]['real'])
                                && ($nPriceAtStartOfDay>$nPreviousDayClosePrice)
//                                && ((($nPriceAtStartOfDay-$nPreviousDayClosePrice)/$nPriceAtStartOfDay)< 0.001) 
                                ){
                            $oRealPrice->addTrade(realPrice::TRADE_BUY, $oRealPrice->getClose());
                            $iTrading = realPrice::TRADE_BUY;
                        }    
                        elseif (($aIndicatorsData['MA'][20]['real']<$aPreviousPriceIndicatorsData['MA'][20]['real'])
                        && ($aIndicatorsData['MA'][10]['real']<$aPreviousPriceIndicatorsData['MA'][10]['real'])
                                && ($nPriceAtStartOfDay<$nPreviousDayClosePrice)
//                                && ((($nPreviousDayClosePrice-$nPriceAtStartOfDay)/$nPreviousDayClosePrice)<0.001)
                                ){
                            $oRealPrice->addTrade(realPrice::TRADE_SELL, $oRealPrice->getClose());
                            $iTrading = realPrice::TRADE_SELL;
                        }
                    }
                    else {
                            if ($iTrading == realPrice::TRADE_BUY){
                                if ($oRealPrice->getMin()<$aIndicatorsData['MA'][20]['real']){
                                    
                                    if ($iPreviousDay!=$iCurrentDay){
                                         $oPreviousRealPrice->addTrade(realPrice::TRADE_CLOSE, $oPreviousRealPrice->getClose());
                                    }
                                    else {
                                        if ($oRealPrice->getMax()<$aIndicatorsData['MA'][20]['real']){ 
                                            $nClosePrice = $oRealPrice->getMax();
                                        }
                                        else {
                                            $nClosePrice = $aIndicatorsData['MA'][20]['real'];
                                        }

                                        $oRealPrice->addTrade(realPrice::TRADE_CLOSE,$nClosePrice);
                                    }
                                    
                                    $iTrading = NULL;
                                }
                            }
                            else {
                                if ($oRealPrice->getMax()>$aIndicatorsData['MA'][20]['real']){
                                    
                                    if ($iPreviousDay!=$iCurrentDay){
                                         $oPreviousRealPrice->addTrade(realPrice::TRADE_CLOSE, $oPreviousRealPrice->getClose());
                                    }
                                    else {
                                        if ($oRealPrice->getMin()>$aIndicatorsData['MA'][20]['real']){ 
                                            $nClosePrice = $oRealPrice->getMin();
                                        }
                                        else {
                                            $nClosePrice = $aIndicatorsData['MA'][20]['real'];
                                        }

                                        $oRealPrice->addTrade(realPrice::TRADE_CLOSE,$nClosePrice);
                                    }
                                    
                                    $iTrading = NULL;
                                }
                            }
                    }
                }
            }
            
            $oPreviousRealPrice = $oRealPrice;
            $aPreviousPriceIndicatorsData = $aIndicatorsData;
            $iPreviousDay = $iCurrentDay;
        }
    }
    
    
    /**
     *RSI 14
     * 
     * AVG
        Gains 50.16
        Loss 101.16
        Failures 0.27
        Composite Gains 36.39
        Composite Losses 27.77
        Ratio Gains/Loss 1.31
        Losses in a row 1.27
        Gains in a row 3.36
     * 
     * sell  48-60
     * buy 40-52
     * AVG
Gains 45.43
Loss 102.84
Failures 0.23
Composite Gains 34.83
Composite Losses 24.00
Ratio Gains/Loss 1.45
Losses in a row 1.40
Gains in a row 4.18
     * 
     * 
     * sell     49-60
     * buy    40-51
     * AVG
Gains 44.48
Loss 106.46
Failures 0.23
Composite Gains 34.10
Composite Losses 24.84
Ratio Gains/Loss 1.37
Losses in a row 1.40
Gains in a row 4.18
     *  
     * 
     * sell   46-60
     * buy  40-54
     * AVG
Gains 48.60
Loss 91.10
Failures 0.28
Composite Gains 34.84
Composite Losses 25.78
Ratio Gains/Loss 1.35
Losses in a row 1.36
Gains in a row 3.45
     * 
     * 
     * Opening price improves 15% gains
     */
    private function _strategy5(){
        $oPreviousRealPrice = NULL;
        $aPreviousPriceIndicatorsData = NULL;
        
        $iPreviousDay = NULL;
        
        $nPriceAtStartOfDay = NULL;
        $nPreviousDayClosePrice = NULL;
        $iTrading = NULL;
        
        foreach($this->_aPrices as $sDateTime=>$oRealPrice){
            $iCurrentDay=substr($sDateTime,11,2);
            $aIndicatorsData = $oRealPrice->getIndicators()->getData();
            
            if (!is_null($iPreviousDay)){
                if ($iPreviousDay!=$iCurrentDay){
                    $nPriceAtStartOfDay = $oRealPrice->getOpen();
                    $nPreviousDayClosePrice = $oPreviousRealPrice->getClose();
                }
                
                if (!is_null($aPreviousPriceIndicatorsData) && !is_null($aPreviousPriceIndicatorsData['RSI']) && !is_null($aPreviousPriceIndicatorsData['RSI'][14]['real'])){
                    if(is_null($iTrading)){
                        if (($aIndicatorsData['RSI'][14]['real']>60) && ($nPriceAtStartOfDay<$nPreviousDayClosePrice)){
                            $oRealPrice->addTrade(realPrice::TRADE_SELL, $oRealPrice->getClose());
                            $iTrading = realPrice::TRADE_SELL;    
                        }
                        elseif (($aIndicatorsData['RSI'][14]['real']<40) && ($nPriceAtStartOfDay>$nPreviousDayClosePrice)){
                            $oRealPrice->addTrade(realPrice::TRADE_BUY, $oRealPrice->getClose());
                            $iTrading = realPrice::TRADE_BUY;
                        }
                    }
                    else {
                        if ((($iTrading == realPrice::TRADE_SELL) && ($aIndicatorsData['RSI'][14]['real']<46))
                            || (($iTrading == realPrice::TRADE_BUY) && ($aIndicatorsData['RSI'][14]['real']>54))){
                            $oRealPrice->addTrade(realPrice::TRADE_CLOSE, $oRealPrice->getClose());
                            $iTrading = NULL;
                        }
                    }
                }
            }
            
            $oPreviousRealPrice = $oRealPrice;
            $aPreviousPriceIndicatorsData = $aIndicatorsData;
            $iPreviousDay = $iCurrentDay;
        }
    }
    
    /**
     *AVG
Gains 67.66
Loss 108.37
Failures 0.33
Composite Gains 45.11
Composite Losses 36.12
Ratio Gains/Loss 1.25
Losses in a row 1.33
Gains in a row 2.67 
     */
    private function _strategy6(){
        $oPreviousRealPrice = NULL;
        $aPreviousPriceIndicatorsData = NULL;
        
        $iPreviousDay = NULL;
        
        $nPriceAtStartOfDay = NULL;
        $nPreviousDayClosePrice = NULL;
        $iTrading = NULL;
        
        foreach($this->_aPrices as $sDateTime=>$oRealPrice){
            $iCurrentDay=substr($sDateTime,11,2);
            $aIndicatorsData = $oRealPrice->getIndicators()->getData();
            
            if (!is_null($iPreviousDay)){
                if ($iPreviousDay!=$iCurrentDay){
                    $nPriceAtStartOfDay = $oRealPrice->getOpen();
                    $nPreviousDayClosePrice = $oPreviousRealPrice->getClose();
                }
                
                if (!is_null($aPreviousPriceIndicatorsData) && !is_null($aPreviousPriceIndicatorsData['STO'])){
                    if(is_null($iTrading)){
                        if (($aIndicatorsData['STO']['real']['d']>$aPreviousPriceIndicatorsData['STO']['real']['d'])
                                && ($aIndicatorsData['STO']['real']['d']<25)
//                                && ($nPriceAtStartOfDay<$nPreviousDayClosePrice)
                                ){
                            $oRealPrice->addTrade(realPrice::TRADE_BUY, $oRealPrice->getClose());
                            $iTrading = realPrice::TRADE_BUY;
                            
                        }
                        elseif (($aIndicatorsData['STO']['real']['d']<$aPreviousPriceIndicatorsData['STO']['real']['d'])
                                && ($aIndicatorsData['STO']['real']['d']>75)
//                                && ($nPriceAtStartOfDay>$nPreviousDayClosePrice)
                                ){
                            $oRealPrice->addTrade(realPrice::TRADE_SELL, $oRealPrice->getClose());
                            $iTrading = realPrice::TRADE_SELL;    
                        }
                    }
                    else {
                        if ((($iTrading == realPrice::TRADE_SELL) && ($aIndicatorsData['STO']['real']['d']<32))
//                                || (($iTrading == realPrice::TRADE_SELL) && ($oRealPrice->getMax()>$oPreviousRealPrice->getMax()))
                            || (($iTrading == realPrice::TRADE_BUY) && ($aIndicatorsData['STO']['real']['d']>68))
//                                || (($iTrading == realPrice::TRADE_BUY) && ($oRealPrice->getMin()<$oPreviousRealPrice->getMin()))
                                ){
                            $oRealPrice->addTrade(realPrice::TRADE_CLOSE, $oRealPrice->getClose());
                            $iTrading = NULL;
                        }
                    }
                }
            }
            
            $oPreviousRealPrice = $oRealPrice;
            $aPreviousPriceIndicatorsData = $aIndicatorsData;
            $iPreviousDay = $iCurrentDay;
        }
    }
    
    
    private function _strategy7(){
        $oPreviousRealPrice = NULL;
        $aPreviousPriceIndicatorsData = NULL;
        
        $iPreviousDay = NULL;
        
        $nPriceAtStartOfDay = NULL;
        $nPreviousDayClosePrice = NULL;
        $iTrading = NULL;
        
        foreach($this->_aPrices as $sDateTime=>$oRealPrice){
            $iCurrentDay=substr($sDateTime,11,2);
            $aIndicatorsData = $oRealPrice->getIndicators()->getData();
            
            if (!is_null($iPreviousDay)){
                if ($iPreviousDay!=$iCurrentDay){
                    $nPriceAtStartOfDay = $oRealPrice->getOpen();
                    $nPreviousDayClosePrice = $oPreviousRealPrice->getClose();
                }
            }
            
            if (!is_null($nPreviousDayClosePrice)){
                if(is_null($iTrading)){
                    if ($iPreviousDay!=$iCurrentDay){
                        if ($nPriceAtStartOfDay>$nPreviousDayClosePrice){
                            $oRealPrice->addTrade(realPrice::TRADE_BUY, $oRealPrice->getClose());
                            $iTrading = realPrice::TRADE_BUY;
                        }
                        else {
                            $oRealPrice->addTrade(realPrice::TRADE_SELL, $oRealPrice->getClose());
                            $iTrading = realPrice::TRADE_SELL;
                        }
                    }
                }
                else {
                    if ($iPreviousDay!=$iCurrentDay){
                        $oPreviousRealPrice->addTrade(realPrice::TRADE_CLOSE, $oPreviousRealPrice->getClose());
                        $iTrading = NULL;
                    }
                }
            }
            
            $oPreviousRealPrice = $oRealPrice;
            $aPreviousPriceIndicatorsData = $aIndicatorsData;
            $iPreviousDay = $iCurrentDay;
        }
    }
    
    
    private function _strategy_daily1(){
        $oPreviousRealPrice = NULL;
        $aPreviousPriceIndicatorsData = NULL;
        
        $iTrading = NULL;
        
        foreach($this->_aPrices as $sDateTime=>$oRealPrice){
            $aIndicatorsData = $oRealPrice->getIndicators()->getData();
            if (!is_null($oPreviousRealPrice) 
                    && !is_null($aIndicatorsData['RSI'][14]['real']) 
                    && !is_null($aIndicatorsData['BOL'])){
                
                $bUp20 = ($aIndicatorsData['MA'][20]['real']>$aPreviousPriceIndicatorsData['MA'][20]['real']);
                $bUp50 = ($aIndicatorsData['MA'][50]['real']>$aPreviousPriceIndicatorsData['MA'][50]['real']);
                
                if (!is_null($iTrading)){
                    if ($aIndicatorsData['SAR']['real']['trend']!=$aPreviousPriceIndicatorsData['SAR']['real']['trend']){
                        $oRealPrice->addTrade(realPrice::TRADE_CLOSE, $oRealPrice->getClose());
                    }
                }
                
                
                if ($aIndicatorsData['SAR']['real']['trend']!=$aPreviousPriceIndicatorsData['SAR']['real']['trend']){

                    if ($aIndicatorsData['SAR']['real']['trend']=='up'){echo 1;
                        $oRealPrice->addTrade(realPrice::TRADE_BUY, $oRealPrice->getClose());
                    }
                    else {
                        $oRealPrice->addTrade(realPrice::TRADE_SELL, $oRealPrice->getClose());
                    }
                }
            }
            
            
            $aRealPriceTrades = $oRealPrice->getTrade();
            
            if (count($aRealPriceTrades) > 0){
                $iCurrentTradeDir = $aRealPriceTrades[count($aRealPriceTrades)-1]['dir'];
                $iTrading = ($iCurrentTradeDir==realPrice::TRADE_CLOSE) ? NULL:$iCurrentTradeDir;
            }
            
            
            
            $oPreviousRealPrice = $oRealPrice;
            $aPreviousPriceIndicatorsData = $aIndicatorsData;
        }
    }
    
    
    
    private function _strategy_daily2(){
        $aExtremes = array();
        $aMA = array();
        $bInTrend = false;
        $bNewTrend = false;
        $aDateTimes = array_keys($this->_aPrices);
        $iDateTimeIndex=0;
        while($iDateTimeIndex < count($aDateTimes)){
            $oRealPrice = $this->_aPrices[$aDateTimes[$iDateTimeIndex]];
            $aIndicatorsData = $oRealPrice->getIndicators()->getData();
            if(!is_null($aIndicatorsData['RSI'][14]['real']) 
               && !is_null($aIndicatorsData['BOL'])
               && !is_null($aIndicatorsData['MA'][20]['real'])){
                
                if (count($aMA)>4){
                    array_shift($aMA);
                }
                $aMA[] = $aIndicatorsData['MA'][20]['real'];
                
                for ($i=1, $iConsecutive=0; $i<count($aMA); $i++){
                    if ($aMA[$i]>$aMA[$i-1]) $iConsecutive++;
                    else $iConsecutive--;
                }
                echo $aDateTimes[$iDateTimeIndex]."   .... $iConsecutive   .... ".$aIndicatorsData['MA'][20]['real']."  \n";
                $bNewTrend=((!$bInTrend) && (abs($iConsecutive)==3));
                
                if (abs($iConsecutive)==4){
                    $bInTrend=true;
                }
                else {
                    $bInTrend=false;
                }
                
                if (empty($aExtremes)){
                    $aExtremes[] = array('min' => $aDateTimes[$iDateTimeIndex]
                                       , 'max' => $aDateTimes[$iDateTimeIndex]);
                }
                else {
                    if ($bNewTrend){
                        
                        if ($this->_aPrices[$aDateTimes[$iDateTimeIndex]]->getClose() > $this->_aPrices[$aExtremes[count($aExtremes)-1]['max']]->getClose()){
                            $aExtremes[count($aExtremes)-1]['max'] = $aDateTimes[$iDateTimeIndex];
                        }
                        if ($this->_aPrices[$aDateTimes[$iDateTimeIndex]]->getClose() < $this->_aPrices[$aExtremes[count($aExtremes)-1]['min']]->getClose()){
                            $aExtremes[count($aExtremes)-1]['min'] = $aDateTimes[$iDateTimeIndex];
                        }
                        
                        $sDateMin = $aExtremes[count($aExtremes)-1]['min'];
                        $sDateMax = $aExtremes[count($aExtremes)-1]['max'];
                        
                        if ($sDateMin>$sDateMax){
                            $iLastLimitIndex = array_search($sDateMin, $aDateTimes);
                        }
                        else {
                            $iLastLimitIndex = array_search($sDateMax, $aDateTimes);
                        }
                        
                        if ($iLastLimitIndex>0){
                            $aDateTimes= array_slice($aDateTimes, $iLastLimitIndex);
                            $iDateTimeIndex = 0;

                            $aExtremes[] = array('min' => $aDateTimes[$iDateTimeIndex]
                                               , 'max' => $aDateTimes[$iDateTimeIndex]);
                        }
                            
                    }
                    else {
                        if ($this->_aPrices[$aDateTimes[$iDateTimeIndex]]->getClose() > $this->_aPrices[$aExtremes[count($aExtremes)-1]['max']]->getClose()){
                            $aExtremes[count($aExtremes)-1]['max'] = $aDateTimes[$iDateTimeIndex];
                        }
                        if ($this->_aPrices[$aDateTimes[$iDateTimeIndex]]->getClose() < $this->_aPrices[$aExtremes[count($aExtremes)-1]['min']]->getClose()){
                            $aExtremes[count($aExtremes)-1]['min'] = $aDateTimes[$iDateTimeIndex];
                        }
                    }
                }
            }
            
            $iDateTimeIndex++;
        }
        
        foreach($aExtremes as $aExtreme){
            if ($aExtreme['min']<$aExtreme['max']) {
                $this->_aPrices[$aExtreme['min']]->addTrade(realPrice::TRADE_BUY, $this->_aPrices[$aExtreme['min']]->getClose());
                $this->_aPrices[$aExtreme['max']]->addTrade(realPrice::TRADE_CLOSE, $this->_aPrices[$aExtreme['max']]->getClose());
            }
            else {
                $this->_aPrices[$aExtreme['max']]->addTrade(realPrice::TRADE_SELL, $this->_aPrices[$aExtreme['max']]->getClose());
                $this->_aPrices[$aExtreme['min']]->addTrade(realPrice::TRADE_CLOSE, $this->_aPrices[$aExtreme['min']]->getClose());
            }
        }
    }
    
    
    
    
    /*
     * backup-strategy-daily2
     */
    private function _strategy_daily3(){
        $iConsecutiveMark = 4;
        $aDateTimes = array_keys($this->_aPrices);
        $aMA = array();
        $aDateTimesConsecutive = array();
        for($i=0;$i < count($aDateTimes);$i++){
            $aIndicatorsData = $this->_aPrices[$aDateTimes[$i]]->getIndicators()->getData();
            if(!is_null($aIndicatorsData['RSI'][14]['real']) 
               && !is_null($aIndicatorsData['BOL'])
               && !is_null($aIndicatorsData['MA'][20]['real'])
               && !is_null($aIndicatorsData['MA'][50]['real'])){
           
                if (count($aMA) > $iConsecutiveMark){
                    array_shift($aMA);
                }
                $aMA[] = $aIndicatorsData['MA'][20]['real'];
                
                for ($i2=1, $iConsecutive=0; $i2<count($aMA); $i2++){
                    if ($aMA[$i2]>$aMA[$i2-1]) $iConsecutive++;
                    else $iConsecutive--;
                }
                
                $aDateTimesConsecutive[] = array('datetime'    => $aDateTimes[$i]
                                               , 'consecutive' => $iConsecutive);
            }
        }
        
        $aDateTimesTrends = array();
        $bInTrend = NULL;
        for($i=0;$i<count($aDateTimesConsecutive);$i++){
            if (empty($aDateTimesTrends)){
                $aDateTimesTrends[] = array('min' => $i
                                           ,'max' => $i);
                $iLastUpdate = $i;
            }
            else {
                if (is_null($bInTrend)){
                    if ($this->_aPrices[$aDateTimesConsecutive[$i]['datetime']]->getClose() > $this->_aPrices[$aDateTimesConsecutive[$aDateTimesTrends[count($aDateTimesTrends)-1]['max']]['datetime']]->getClose()){
                        $aDateTimesTrends[count($aDateTimesTrends)-1]['max'] = $i;
                        $iLastUpdate = $i;
                    }

                    if ($this->_aPrices[$aDateTimesConsecutive[$i]['datetime']]->getClose() < $this->_aPrices[$aDateTimesConsecutive[$aDateTimesTrends[count($aDateTimesTrends)-1]['min']]['datetime']]->getClose()){
                        $aDateTimesTrends[count($aDateTimesTrends)-1]['min'] = $i;
                        $iLastUpdate = $i;
                    }
                    
                    if (($aDateTimesConsecutive[$i]['consecutive']==$iConsecutiveMark)) $bInTrend = true;
                }
                else {
                    if (($aDateTimesConsecutive[$i]['consecutive']==$iConsecutiveMark)) {
                        if ($bInTrend){
                            if ($this->_aPrices[$aDateTimesConsecutive[$i]['datetime']]->getClose() > $this->_aPrices[$aDateTimesConsecutive[$aDateTimesTrends[count($aDateTimesTrends)-1]['max']]['datetime']]->getClose()){
                                $aDateTimesTrends[count($aDateTimesTrends)-1]['max'] = $i;
                                $iLastUpdate = $i;
                            }

                            if ($this->_aPrices[$aDateTimesConsecutive[$i]['datetime']]->getClose() < $this->_aPrices[$aDateTimesConsecutive[$aDateTimesTrends[count($aDateTimesTrends)-1]['min']]['datetime']]->getClose()){
                                $aDateTimesTrends[count($aDateTimesTrends)-1]['min'] = $i;
                                $iLastUpdate = $i;
                            }
                        }
                        else {
                            $aDateTimesTrends[] = array('min' => $iLastUpdate
                                                       ,'max' => $iLastUpdate);
                            for($i2=$iLastUpdate;$i2<=$i;$i2++){
                                if ($this->_aPrices[$aDateTimesConsecutive[$i2]['datetime']]->getClose() > $this->_aPrices[$aDateTimesConsecutive[$aDateTimesTrends[count($aDateTimesTrends)-1]['max']]['datetime']]->getClose()){
                                    $aDateTimesTrends[count($aDateTimesTrends)-1]['max'] = $i2;
                                    $iLastUpdate = $i2;
                                }

                                if ($this->_aPrices[$aDateTimesConsecutive[$i2]['datetime']]->getClose() < $this->_aPrices[$aDateTimesConsecutive[$aDateTimesTrends[count($aDateTimesTrends)-1]['min']]['datetime']]->getClose()){
                                    $aDateTimesTrends[count($aDateTimesTrends)-1]['min'] = $i2;
                                    $iLastUpdate = $i2;
                                }
                            }
                            $bInTrend = true;
                        }
                    }
                    else {
                        if ($this->_aPrices[$aDateTimesConsecutive[$i]['datetime']]->getClose() > $this->_aPrices[$aDateTimesConsecutive[$aDateTimesTrends[count($aDateTimesTrends)-1]['max']]['datetime']]->getClose()){
                            $aDateTimesTrends[count($aDateTimesTrends)-1]['max'] = $i;
                            $iLastUpdate = $i;
                        }

                        if ($this->_aPrices[$aDateTimesConsecutive[$i]['datetime']]->getClose() < $this->_aPrices[$aDateTimesConsecutive[$aDateTimesTrends[count($aDateTimesTrends)-1]['min']]['datetime']]->getClose()){
                            $aDateTimesTrends[count($aDateTimesTrends)-1]['min'] = $i;
                            $iLastUpdate = $i;
                        }
                        $bInTrend = false;
                    }
                }
            }
        }
        
        foreach($aDateTimesTrends as $i=>$aTrend){
            if ($aDateTimesConsecutive[$aTrend['min']]['datetime']<$aDateTimesConsecutive[$aTrend['max']]['datetime']) {
                $this->_aPrices[$aDateTimesConsecutive[$aTrend['min']]['datetime']]->addTrade(realPrice::TRADE_BUY, $this->_aPrices[$aDateTimesConsecutive[$aTrend['min']]['datetime']]->getClose());
                $this->_aPrices[$aDateTimesConsecutive[$aTrend['max']]['datetime']]->addTrade(realPrice::TRADE_CLOSE, $this->_aPrices[$aDateTimesConsecutive[$aTrend['max']]['datetime']]->getClose());
            }
            else {
                $this->_aPrices[$aDateTimesConsecutive[$aTrend['max']]['datetime']]->addTrade(realPrice::TRADE_SELL, $this->_aPrices[$aDateTimesConsecutive[$aTrend['max']]['datetime']]->getClose());
                $this->_aPrices[$aDateTimesConsecutive[$aTrend['min']]['datetime']]->addTrade(realPrice::TRADE_CLOSE, $this->_aPrices[$aDateTimesConsecutive[$aTrend['min']]['datetime']]->getClose());
            }
        }
        
        $aTrendData = array();

        foreach($aDateTimesTrends as $i=>$aTrend){
            $bUpTrend = ($aDateTimesConsecutive[$aTrend['min']]['datetime']<$aDateTimesConsecutive[$aTrend['max']]['datetime']);
            if ($bUpTrend) {
                $oRealPriceStart = $this->_aPrices[$aDateTimesConsecutive[$aTrend['min']]['datetime']];
                $oRealPriceEnd = $this->_aPrices[$aDateTimesConsecutive[$aTrend['max']]['datetime']];
                
                $aDownIndicatorsData = $oRealPriceStart->getIndicators()->getData();
                $aUpIndicatorsData = $oRealPriceEnd->getIndicators()->getData();
            }
            else {
                $oRealPriceStart = $this->_aPrices[$aDateTimesConsecutive[$aTrend['max']]['datetime']];
                $oRealPriceEnd = $this->_aPrices[$aDateTimesConsecutive[$aTrend['min']]['datetime']];
                
                $aUpIndicatorsData = $oRealPriceStart->getIndicators()->getData();
                $aDownIndicatorsData = $oRealPriceEnd->getIndicators()->getData();
            }
            
            $aTrendData[] = array('trend'   => (($bUpTrend) ? "UP":"DOWN")
                                 ,'start'   => $oRealPriceStart->getClose()
                                 ,'end'     => $oRealPriceEnd->getClose()
                                 ,'down_rsi'     => $aDownIndicatorsData['RSI'][14]['real']
                                 ,'down_bol_up'  => $aDownIndicatorsData['BOL']['real']['up']
                                 ,'down_bol_down' => $aDownIndicatorsData['BOL']['real']['down']
                                 ,'down_ma_20'   => $aDownIndicatorsData['MA'][20]['real']
                                 ,'down_sto_k'   => $aDownIndicatorsData['STO']['real']['k']
                                 ,'down_sto_d'   => $aDownIndicatorsData['STO']['real']['d']
                                 ,'down_psar'    => $aDownIndicatorsData['SAR']['real']['psar']
                                 ,'down_sar_trend' => $aDownIndicatorsData['SAR']['real']['trend']
                                 ,'up_rsi'      => $aUpIndicatorsData['RSI'][14]['real']
                                 ,'up_bol_up'   => $aUpIndicatorsData['BOL']['real']['up']
                                 ,'up_bol_down' => $aUpIndicatorsData['BOL']['real']['down']
                                 ,'up_ma_20'    => $aUpIndicatorsData['MA'][20]['real']
                                 ,'up_sto_k'    => $aUpIndicatorsData['STO']['real']['k']
                                 ,'up_sto_d'    => $aUpIndicatorsData['STO']['real']['d']
                                 ,'up_psar'     => $aUpIndicatorsData['SAR']['real']['psar']
                                 ,'up_sar_trend' => $aUpIndicatorsData['SAR']['real']['trend']
                                 ,'ma50_pct' => (($bUpTrend) ? (100*($aDownIndicatorsData['MA'][50]['real']-$oRealPriceStart->getClose())/$oRealPriceStart->getClose()):
                                                               (100*($oRealPriceStart->getClose()-$aUpIndicatorsData['MA'][50]['real'])/$oRealPriceStart->getClose()))
                                 ,'profit'      => NULL
                                 ,'profit_pct'  => NULL);

        }
        
        
        if (!empty($aTrendData)){
            echo "<table>";
            echo "<tr><td>".implode('</td><td>',array_keys($aTrendData[0]))."</td></tr>";

            for($i=0;$i<count($aTrendData);$i++){
                if ($aTrendData[$i]['trend']=='UP'){
                    $aTrendData[$i-count($aDateTimesTrends)]=$aTrendData[$i];
                    unset($aTrendData[$i]);
                }
            }
            ksort($aTrendData);
            foreach(array_keys($aTrendData) as $i){
                $aTrendData[$i]['profit'] = abs($aTrendData[$i]['start']-$aTrendData[$i]['end']);
                $aTrendData[$i]['profit_pct'] = 100*($aTrendData[$i]['profit']/$aTrendData[$i]['start']);
                echo "<tr><td>".implode('</td><td>',$aTrendData[$i])."</td></tr>";
            }
            echo "</table>";
        }
        
        
        $aRSI = array();
        while(list($key,$aData) = each($aTrendData)){
            $iRSIKey = ((int)($aTrendData[$key]['down_rsi']/2));
            if (array_key_exists($iRSIKey,$aRSI)){
                $aRSI[$iRSIKey] += $aTrendData[$key]['profit_pct'];
            }
            else {
                $aRSI[$iRSIKey] = $aTrendData[$key]['profit_pct'];
            }
        }
        
        ksort($aRSI);
        
        $sHTMLRSIProffit = "<table>";
        $sHTMLRSIProffit .= "<tr><td>RSI</td><td>Proffit</td></tr>";
        while(list($iRSI,$nProffit)=each($aRSI)){
            $iRSI*=2;
            $sHTMLRSIProffit .= "<tr><td>$iRSI</td><td>$nProffit</td></tr>";
        }
        $sHTMLRSIProffit .= "</table>";
        
        echo $sHTMLRSIProffit;
        
        
        
        
        $aSTO = array();
        reset($aTrendData);
        while(list($key,$aData) = each($aTrendData)){
            $iSTOk = ((int)($aTrendData[$key]['down_sto_k']/2));
            if (array_key_exists($iSTOk,$aSTO)){
                if (array_key_exists('k',$aSTO[$iSTOk])){
                    $aSTO[$iSTOk]['k'] += $aTrendData[$key]['profit_pct'];
                }
                else {
                    $aSTO[$iSTOk]['k'] = $aTrendData[$key]['profit_pct'];
                }
            }
            else {
                $aSTO[$iSTOk] = array('k' => $aTrendData[$key]['profit_pct']);
            }
            echo 1;
            $iSTOd = ((int)($aTrendData[$key]['down_sto_d']/2));
            if (array_key_exists($iSTOd,$aSTO)){
                if (array_key_exists('d', $aSTO[$iSTOd])){
                    $aSTO[$iSTOd]['d'] += $aTrendData[$key]['profit_pct'];
                }
                else {
                    $aSTO[$iSTOd]['d'] = $aTrendData[$key]['profit_pct'];
                }
                
            }
            else {
                $aSTO[$iSTOd] = array('d' => $aTrendData[$key]['profit_pct']);
            }
        }
        ksort($aSTO);
        
        $sHTMLSTOProffit = "<table>";
        $sHTMLSTOProffit .= "<tr><td>STO Value</td><td>Proffit K</td><td>Proffit D</td></tr>";
        while(list($iSTOValue,$aSTOData)=each($aSTO)){
            
            $nProffitK = array_key_exists('k',$aSTO[$iSTOValue]) ? $aSTO[$iSTOValue]['k'] :0;
            $nProffitD = array_key_exists('d',$aSTO[$iSTOValue]) ? $aSTO[$iSTOValue]['d'] :0;
            $iSTOValue*=2;
            
            $sHTMLSTOProffit .= "<tr><td>$iSTOValue</td><td>$nProffitK</td><td>$nProffitD</td></tr>";
        }
        $sHTMLSTOProffit .= "</table>";
        
        echo $sHTMLSTOProffit;
        
    }
    
    
    private function _strategy_daily4(){
        $iTrading = NULL;
        $aDateTimes = array_keys($this->_aPrices);
        $aLast = array('up'=>array(), 'down'=>array());
        $iRefDays = 20;
        $nOpenPrice = NULL;
        $bReadyToSell = false;
        $bReadyToBuy = false;
                    
        for($i=0;$i < count($aDateTimes);$i++){
            $oRealPrice = $this->_aPrices[$aDateTimes[$i]];
            $aIndicatorsData = $oRealPrice->getIndicators()->getData();
            if(!is_null($aIndicatorsData['RSI'][14]['real']) 
                && !is_null($aIndicatorsData['STO'])
                && !is_null($aIndicatorsData['MA'][20]['real'])
                && !is_null($aIndicatorsData['MA'][50]['real'])){
                
                if (count($aLast['up'])>=$iRefDays){
                    array_shift($aLast['up']);
                    array_shift($aLast['down']);
                }
                $aLast['up'][] = $oRealPrice->getMax();
                $aLast['down'][] = $oRealPrice->getMin();
               
                
                // Positive -> price is belowe MA50  -   Negative -> price is above MA50
                $nMA50DistancePct = 100*($aIndicatorsData['MA'][50]['real']-$oRealPrice->getClose())/$oRealPrice->getClose();
                
                $nCoef = $aIndicatorsData['RSI'][14]['real']*$aIndicatorsData['STO']['real']['d'];
                
                $bOverBought    = (($nMA50DistancePct<-5) &&  ($nCoef>6800));
                $bOverSold      = (($nMA50DistancePct>4) && ($nCoef<900));
                
                $bBelowRefPrice = (count($aLast['down'])>=8) ? ($oRealPrice->getMin()<min(array_slice($aLast['down'],5,14))):false;
                $bAboveRefPrice = (count($aLast['up'])>=8) ? ($oRealPrice->getMax()>max(array_slice($aLast['up'],5,14))):false;
                        
                if (($bOverBought) || $bBelowRefPrice){
                    if (!$bReadyToSell){
                        $bReadyToSell = true;
                        $bReadyToBuy = false;
                    }    
                }
                elseif($bOverSold || $bAboveRefPrice){
                    if (!$bReadyToBuy){
                        $bReadyToBuy = true;
                        $bReadyToSell = false;
                    }
                }
                
                if (!is_null($iTrading)){
                    if ($iTrading==realPrice::TRADE_BUY) {
                        if(($bReadyToSell) && ($oRealPrice->getMin()<min(array_slice($aLast['down'],5,4)))){
                            
                            $oRealPrice->addTrade(realPrice::TRADE_CLOSE, $oRealPrice->getClose());
                            $iTrading=NULL;
                            
                            $nOpenPrice = $oRealPrice->getClose();
                            $oRealPrice->addTrade(realPrice::TRADE_SELL, $oRealPrice->getClose());
                            $nOpenDistance = $nMA50DistancePct;
                            $iTrading=realPrice::TRADE_SELL;
                        }
                    }
                    elseif ($iTrading==realPrice::TRADE_SELL){
                        if($bReadyToBuy && ($oRealPrice->getMax()>max(array_slice($aLast['up'],5,4)))){
                            $oRealPrice->addTrade(realPrice::TRADE_CLOSE, $oRealPrice->getClose());
                            $iTrading=NULL;
                            $nOpenPrice = $oRealPrice->getClose();
                            $oRealPrice->addTrade(realPrice::TRADE_BUY, $oRealPrice->getClose());
                            $nOpenDistance = $nMA50DistancePct;
                            $iTrading=realPrice::TRADE_BUY;
                        }
                    }
                }
                
                
                if (is_null($iTrading)){
                    
//                    echo "$nMA50DistancePct {$aIndicatorsData['RSI'][14]['real']} {$aIndicatorsData['STO']['real']['d']} <br/>";
                    
                    if ((($nMA50DistancePct>4)
//                        && ($aIndicatorsData['RSI'][14]['real']<49)    
//                        && ($aIndicatorsData['STO']['real']['d']<38)    
                          &&  ($nCoef<1800))
                            
//                          || ($oRealPrice->getMax()>max(array_slice($aLast['up'],0,9)))
                        ){
                        $nOpenPrice = $oRealPrice->getClose();
                        $oRealPrice->addTrade(realPrice::TRADE_BUY, $oRealPrice->getClose());
                        $nOpenDistance = $nMA50DistancePct;
                        $iTrading=realPrice::TRADE_BUY;
                        $bReadyToBuy = false;
                        $bReadyToSell = false;
                    }
                    elseif ((($nMA50DistancePct<-4)
//                        && ($aIndicatorsData['RSI'][14]['real']>73)    
//                        && ($aIndicatorsData['STO']['real']['d']<15)    
                            &&  ($nCoef>6905)) // 73*85
//                            || ($oRealPrice->getMin()<min(array_slice($aLast['down'],0,9)))
                        ){
                        $nOpenPrice = $oRealPrice->getClose();
                        $oRealPrice->addTrade(realPrice::TRADE_SELL, $oRealPrice->getClose());
                        $nOpenDistance = $nMA50DistancePct;
                        $iTrading=realPrice::TRADE_SELL;
                        $bReadyToBuy = false;
                        $bReadyToSell = false;
                    }
                }
            }
        }
    }
}
?>
