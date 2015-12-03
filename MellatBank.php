<?php

namespace mihandev\gateway;

use yii\helpers\Url;
use yii\bootstrap\Html;
use yii\widgets\ActiveForm;
use yii\web\NotFoundHttpException;

class MellatBank extends \yii\base\Widget {

    /**
     * @var array options 
     */
    public $options = [];

    /**
     * @var integer amount
     */
    public $amount;

    /**
     * Run widget
     * @return string
     */
    public function run() {
        ActiveForm::begin(['action' => $this->options['url']]);
        echo Html::hiddenInput('MellatBank[payment]', 1);
        if(isset($this->amount) && $this->amount > 100) echo Html::hiddenInput('MellatBank[amount]', $this->amount);
        echo Html::submitButton(isset($this->options['title']) ? $this->options['title'] : 'پرداخت', ['class' => isset($this->options['buttonClass']) ? $this->options['buttonClass'] : 'btn btn-success']);
        ActiveForm::end();

        return;
    }

    /**
     * Start new payment
     * By this method you can start a new payment like this:
     * 
     * ```php
     * $mellatbank = new \mihandev\gateway\MellatBank();
     * return $mellatbank->startPayment([
     *      'terminal' => 000000,
     *      'username' => 'username',
     *      'password' => 'password',
     *      'amount' => 1000,
     *      'callBackUrl' => ['/site/callback']
     * ]);
     * ```
     * 
     * @param array $config
     * @return mixed
     */
    public function startPayment(array $config) {
        $client = new \nusoap_client('https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl');
        $err = $client->getError();
        if ($err) {
            echo '<h2>Constructor error</h2><pre>' . $err . '</pre>';
            die();
        }

        $parameters = [
            'terminalId' => $config['terminal'],
            'userName' => $config['username'],
            'userPassword' => $config['password'],
            'orderId' => isset($config['orderId']) ? $config['orderId'] : rand(10000, 99999),
            'amount' => $config['amount'],
            'localDate' => date('ymj'),
            'localTime' => date('His'),
            'additionalData' => '',
            'callBackUrl' => Url::to($config['callBackUrl'], TRUE),
            'payerId' => 0
        ];

        // save parameters in session
        $session = \Yii::$app->getSession();
        $session['mellatbank'] = $parameters;

        // send request to bank
        $result = $client->call('bpPayRequest', $parameters, 'http://interfaces.core.sw.bps.com/');

        if ($client->fault) {
            echo '<h2>Fault</h2><pre>';
            print_r($result);
            echo '</pre>';
            die();
        } else {
            $resultStr = $result;
            $err = $client->getError();
            if ($err) {
                echo '<h2>Error</h2><pre>' . $err . '</pre>';
                die();
            } else {
                $res = explode(',', $resultStr);
                echo '<div style="display:none;">Pay Response is : ' . $resultStr . '</div>';
                $ResCode = $res[0];
                if ($ResCode == "0") {
                    return $this->postRefId($res[1]);
                } else {
                    $this->getError($ResCode);
                }
            }
        }
    }
    
    /**
     * Verify payment
     * send verify payment to bank and return the result.
     * 
     * @param array $config
     * @param array $params
     * @return boolean
     */
    protected function verifyPayment(array $config, array $params) {
        $client = new \nusoap_client('https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl');
        $orderId = $params["SaleOrderId"];
        $verifySaleOrderId = $params["SaleOrderId"];
        $verifySaleReferenceId = $params['SaleReferenceId'];
        $err = $client->getError();
        if ($err) {
            echo '<h2>Constructor error</h2><pre>' . $err . '</pre>';
            die();
        }
        
        // set parameters
        $parameters = [
            'terminalId' => $config['terminal'],
            'userName' => $config['username'],
            'userPassword' => $config['password'],
            'orderId' => $orderId,
            'saleOrderId' => $verifySaleOrderId,
            'saleReferenceId' => $verifySaleReferenceId
        ];
        
        // send request to bank
        $result = $client->call('bpVerifyRequest', $parameters, 'http://interfaces.core.sw.bps.com/');
        if ($client->fault) {
            echo '<h2>Fault</h2><pre>';
            print_r($result);
            echo '</pre>';
            die();
        } else {
            $resultStr = $result;
            $err = $client->getError();
            if ($err) {
                echo '<h2>Error</h2><pre>' . $err . '</pre>';
                die();
            } else {
                if ($resultStr == '0') {
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * Settle payment
     * Send settle request to bank and return the result
     * 
     * @param array $config
     * @param array $params
     * @return boolean
     */
    protected function settlePayment(array $config, array $params) {
        $client = new \nusoap_client('https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl');
        $orderId = $params["SaleOrderId"];
        $settleSaleOrderId = $params["SaleOrderId"];
        $settleSaleReferenceId = $params['SaleReferenceId'];
        $err = $client->getError();
        if ($err) {
            echo '<h2>Constructor error</h2><pre>' . $err . '</pre>';
            die();
        }
        
        // set parameters
        $parameters = [
            'terminalId' => $config['terminal'],
            'userName' => $config['username'],
            'userPassword' => $config['password'],
            'orderId' => $orderId,
            'saleOrderId' => $settleSaleOrderId,
            'saleReferenceId' => $settleSaleReferenceId
        ];
        
        // send settle request to bank
        $result = $client->call('bpSettleRequest', $parameters, 'http://interfaces.core.sw.bps.com/');
        if ($client->fault) {
            echo '<h2>Fault</h2><pre>';
            print_r($result);
            echo '</pre>';
            die();
        } else {
            $resultStr = $result;
            $err = $client->getError();
            if ($err) {
                echo '<h2>Error</h2><pre>' . $err . '</pre>';
                die();
            } else {
                if ($resultStr == '0') {
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * Check if payment is success
     * check and if payment is success send a array or null if it is not.
     *  
     * ```php
     * $mellatbank = new \mihandev\gateway\MellatBank();
     * $config = [
     *      'terminal' => 000000,
     *      'username' => 'username',
     *      'password' => 'password',
     *      'amount' => 1000,
     * ];
     * 
     * $result = $mellatbank->checkPayment($config, $_POST);
     * if($result !== null && $result["status"] == "success") {
     *      payment is success ...
     * }
     * ```
     * 
     * @param array $config
     * @param array $params
     * @return array
     */
    public function checkPayment(array $config, array $params)
    {
        $session = \Yii::$app->getSession();
        if ($params["ResCode"] == 0 && $config['amount'] == $session['mellatbank']['amount']) {
            if ($this->verifyPayment($config, $params) == true) {
                if ($this->settlePayment($config, $params) == true) {
                    return array(
                        "status" => "success",
                        "trans" => $params["SaleReferenceId"]
                    );
                }
            }
        }
        return null;
    }

    /**
     * create a form and submit
     * @param hash $refIdValue
     */
    protected function postRefId($refIdValue) {
        echo '<script language="javascript" type="text/javascript">
        function postRefId (refIdValue) {
            var form = document.createElement("form");
            form.setAttribute("method", "POST");
            form.setAttribute("action", "https://bpm.shaparak.ir/pgwchannel/startpay.mellat");         
            form.setAttribute("target", "_self");
            var hiddenField = document.createElement("input");              
            hiddenField.setAttribute("name", "RefId");
            hiddenField.setAttribute("value", refIdValue);
            form.appendChild(hiddenField);
            document.body.appendChild(form);         
            form.submit();
            document.body.removeChild(form);
        }
        postRefId("' . $refIdValue . '");
        </script>';
    }

    /**
     * Show bank error
     * 
     * @param integer $number
     * @throws NotFoundHttpException
     */
    protected function getError($number)
    {
        $err = 'Error code : ' . $number;
        switch ($number) {
            case 31     : $err = "پاسخ نامعتبر است!";                       break;
            case 17     : $err = "کاربر از انجام تراکنش منصرف شده است!";    break;
            case 21     : $err = "پذیرنده نامعتبر است!";                    break;
            case 25     : $err = "مبلغ نامعتبر است!";                       break;
            case 34     : $err = "خطای سیستمی!";                            break;
            case 41     : $err = "شماره درخواست تکراری است!";               break;
            case 421    : $err = "ای پی نامعتبر است!";                      break;
            case 412    : $err = "شناسه قبض نادرست است!";                   break;
            case 45     : $err = "تراکنش از قبل ستل شده است";               break;
            case 46     : $err = "تراکنش ستل شده است";                      break;
            case 35     : $err = "تاریخ نامعتبر است";                       break;
            case 32     : $err = "فرمت اطلاعات وارد شده صحیح نمیباشد";       break;
            case 43     : $err = "درخواست verify قبلا صادر شده است";         break;
        }
        throw new NotFoundHttpException($err);
    }

}
