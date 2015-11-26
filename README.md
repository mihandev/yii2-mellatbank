Iranian bank mellat gateway library
===================================
you can use this library for bank mellat gateway payments.

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist mihandev/yii2-mellatbank "*"
```

or add

```
"mihandev/yii2-mellatbank": "*"
```

to the require section of your `composer.json` file.


Usage
-----

Once the extension is installed, for show payment button use like this  :

```php
<?= \mihandev\gateway\MellatBank::widget([
    'options' => [
        'url' => ['/site/pay'], // url to send post data
    ]
]); ?>
```

for payment in controller SiteController/actionPay use like this :

```php
public function actionPay() {
    $this->layout = false;
    $params = Yii::$app->request->post('MellatBank');
    if($params !== null) {
        $mellatbank = new \mihandev\gateway\MellatBank();
        return $mellatbank->startPayment([
            'terminal' => 000000,
            'username' => 'yourUsername',
            'password' => 'yourPassword',
            'amount' => 1000,
            'callBackUrl' => ['/site/callback']
        ]);
    }
}
```

for verify payment in SiteController/actionCallback use like this:

```php
public function actionCallback() {
    $mellatbank = new \mihandev\gateway\MellatBank();
    $config = [
        'terminal' => 000000,
        'username' => 'yourUsername',
        'password' => 'yourPassword',
        'amount' => 1000,
    ];

    $result = $mellatbank->checkPayment($config, $_POST);
    if($result !== null && $result["status"] == "success") {
        // payment is success ...
    }
}
```