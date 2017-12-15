<?php

namespace Hunter\pay\Controller;

use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\Response\JsonResponse;
use Zend\Diactoros\Response;
use Yansongda\Pay\Pay;
use BaconQrCode\Renderer\Image\Png;

/**
 * Class Pay.
 *
 * @package Hunter\pay\Controller
 */
class PayController {

  protected $pay_config;

  /**
   * Constructs a pay config.
   */
  public function __construct() {
    $this->pay_config = config('pay.pay')->get('pay_config');
  }

  /**
   * payPage.
   *
   * @return string
   *   Return payPage string.
   */
  public function payPage(ServerRequest $request) {
    return view('/hunter/pay.html');
  }

  /**
   * payAlipay.
   *
   * @return string
   *   Return payAlipay string.
   */
  public function payAlipay(ServerRequest $request) {
    $out = '';
    if ($parms = $request->getParsedBody()) {
      $user = session()->get('admin');
      //添加支付记录
      $out_trade_no = time();
      $pid = db_insert('pay_record')
        ->fields(array(
          'out_trade_no' => $out_trade_no,
          'pay_type' => 'alipay',
          'product_type' => $parms['product_type'],
          'name' => $parms['name'],
          'product_id' => $parms['product_id'],
          'status' => 0,
          'product_content' => $parms['product_content'],
          'total_fee' => $parms['total_amount'],
          'month_num' => isset($parms['month_num']) ? $parms['month_num'] : '',
          'role_type' => isset($parms['role_type']) ? $parms['role_type'] : '',
          'uid' => isset($user->uid) ? $user->uid : 0,
          'created' => time(),
          'updated' => time(),
        ))
        ->execute();

      if($pid){
        $config_biz = [
          'out_trade_no' => $out_trade_no,
          'total_amount' => $parms['total_amount'],
          'subject'      => $parms['name']
        ];

        $pay = new Pay($this->pay_config);

        $out = $pay->driver('alipay')->gateway()->pay($config_biz);
      }
    }

    return $out;
  }

  /**
   * payWechatScan.
   *
   * @return string
   *   Return payWechatScan string.
   */
  public function payWechatScan(ServerRequest $request) {
    if ($parms = $request->getParsedBody()) {
      //添加支付记录
      $out_trade_no = time();
      $pid = db_insert('pay_record')
        ->fields(array(
          'out_trade_no' => $out_trade_no,
          'pay_type' => 'wechat',
          'product_type' => $parms['product_type'],
          'name' => $parms['name'],
          'product_id' => $parms['product_id'],
          'status' => 0,
          'product_content' => $parms['product_content'],
          'total_fee' => $parms['total_amount'],
          'month_num' => isset($parms['month_num']) ? $parms['month_num'] : '',
          'role_type' => isset($parms['role_type']) ? $parms['role_type'] : '',
          'uid' => isset($user->uid) ? $user->uid : 0,
          'created' => time(),
          'updated' => time(),
        ))
        ->execute();

      if($pid){
        $config_biz = [
          'out_trade_no' => $out_trade_no,        // 订单号
          'total_fee' => $parms['total_amount'],  // 订单金额，**单位：分**
          'body' => $parms['name'],               // 订单描述
          'spbill_create_ip' => '8.8.8.8',        // 调用 API 服务器的 IP
          'product_id' => $parms['product_id'],   // 订单商品 ID
        ];

        $pay = new Pay($this->pay_config);

        $wechat_pay_url = $pay->driver('wechat')->gateway('scan')->pay($config_biz);

        if(is_string($wechat_pay_url)){
          $renderer = new \BaconQrCode\Renderer\Image\Png();
          $renderer->setHeight(256);
          $renderer->setWidth(256);
          $renderer->setMargin(0);
          $writer = new \BaconQrCode\Writer($renderer);
          $qrcode_output = $writer->writeString($wechat_pay_url, 'UTF-8');

          $response = new Response();
          $response->getBody()->write($qrcode_output);
          return $response->withAddedHeader('content-type', 'image/png');
        }
      }
    }
  }

  /**
   * payWechatMp.
   *
   * @return string
   *   Return payWechatMp string.
   */
  public function payWechatMp(ServerRequest $request) {
    if ($parms = $request->getParsedBody()) {
      //添加支付记录
      $out_trade_no = time();
      $pid = db_insert('pay_record')
        ->fields(array(
          'out_trade_no' => $out_trade_no,
          'pay_type' => 'wechat',
          'product_type' => $parms['product_type'],
          'name' => $parms['name'],
          'product_id' => $parms['product_id'],
          'status' => 0,
          'product_content' => $parms['product_content'],
          'total_fee' => $parms['total_amount'],
          'month_num' => isset($parms['month_num']) ? $parms['month_num'] : '',
          'role_type' => isset($parms['role_type']) ? $parms['role_type'] : '',
          'uid' => isset($user->uid) ? $user->uid : 0,
          'created' => time(),
          'updated' => time(),
        ))
        ->execute();

      if($pid){
        //公众号支付
        $config_biz = [
          'out_trade_no' => $out_trade_no,
          'total_fee' => $parms['total_amount'], // **单位：分**
          'body' => $parms['name'],
          'spbill_create_ip' => '8.8.8.8',
          'openid' => 'oWjczwCr8QLWQHzFxwe7UbfuZq1k',
          'device_info' => 'WEB',
        ];

        $pay = new Pay($this->pay_config);

        $wechat_pay_parms = $pay->driver('wechat')->gateway('mp')->pay($config_biz);

        if(is_array($wechat_pay_parms)){
          return new JsonResponse($wechat_pay_parms);
        }
        return new JsonResponse(false);
      }

      return new JsonResponse(false);
    }
  }

  /**
   * payAlipayReturn.
   *
   * @return string
   *   Return payAlipayReturn string.
   */
  public function payAlipayReturn(ServerRequest $request) {
    if($parms = $data = $request->getQueryParams()){
      $pay = new Pay($this->pay_config);
      $out = $pay->driver('alipay')->gateway()->verify($data);

      if(!$out) {
        hunter_set_message('验证失败', 'error');
      }

      //验证通过后,再验证其他信息，并在成功后更新支付记录状态
      $record = get_pay_record_bytrade_no($parms['out_trade_no']);
      if ($record
      && $parms['total_amount'] * 100 == $record->total_fee * 100
      && $parms['app_id'] == $this->pay_config['alipay']['app_id']) {
        db_update('pay_record')
          ->fields(array(
            'status' => 1,
            'updated' => time(),
          ))
          ->condition('out_trade_no', $parms['out_trade_no'])
          ->execute();
      }else{
        hunter_set_message('非法请求', 'error');
      }

      //验证通过后,如果商品类型是会员续费，则添加用户角色及有效期, 请自行修改逻辑
      // if($record->product_type == 'xufei'){
      //   $adddays = $record->month_num;
      //   $uid = $record->uid;
      //   $account = get_user_byid($uid);
      //   $account->role_start_time = date('Y-m-d\T00:00:00', time());
      //   $account->role_end_time = date('Y-m-d\T00:00:00', strtotime("+$adddays day"));
      //   $account->role = $record->role_type;
      //   $account->save();
      // }

      $role_type = '';
      if($record->role_type == 'gaojiyonghu') {
        $record->role_type = "高级用户";
      }elseif ($record->role_type == 'fufeiyonghu') {
        $record->role_type = "付费用户";
      }else {
        $record->role_type = "普通用户";
      }
    }

    return view('/hunter/pay-return.html', array('parms' => $record));
  }

  /**
   * payAlipayNotify.
   *
   * @return string
   *   Return payAlipayNotify string.
   */
  public function payAlipayNotify(ServerRequest $request) {
    $pay = new Pay($this->pay_config);
    $parms = $request->getParsedBody();
    if ($pay->driver('alipay')->gateway()->verify($parms)) {
        // 验证通过后,对其他参数进行验证
        $record = get_pay_record_bytrade_no($parms['out_trade_no']);
        if ($parms['trade_status'] === "TRADE_SUCCESS"
        && $record
        && $parms['total_amount'] * 100 == $record->total_fee * 100
        && $parms['app_id'] == $this->pay_config['alipay']['app_id']) {
          $message = "收到来自支付宝的异步通知\r\n";
          $message .= '订单号：' .$parms['out_trade_no']. "\r\n";
          $message .= '订单金额：' .$parms['total_amount']. "\r\n\r\n";
          hunter_set_message($message);

          //验证通过后, 更新支付记录状态
          db_update('pay_record')
            ->fields(array(
              'status' => 1,
              'updated' => time(),
            ))
            ->condition('out_trade_no', $parms['out_trade_no'])
            ->execute();

          return 'success';
        }
        return 'fail';
    } else {
      return 'fail';
    }
  }

  /**
   * payWechatNotify.
   *
   * @return string
   *   Return payWechatNotify string.
   */
  public function payWechatNotify(ServerRequest $request) {
      $pay = new Pay($this->pay_config);
      $verify = $pay->driver('wechat')->gateway('mp')->verify($request->getContent());

      if ($verify) {
        //验证通过后,对其他参数进行验证
        $record = get_pay_record_bytrade_no($verify['out_trade_no']);
        if (array_key_exists("return_code", $verify)
  			&& array_key_exists("result_code", $verify)
  			&& $verify["return_code"] == "SUCCESS"
  			&& $verify["result_code"] == "SUCCESS"
        && $record
        && $verify['total_fee'] * 100 == $record->total_fee * 100
        && $verify['appid'] == $this->pay_config['wechat']['app_id']) {

          $message = "收到来自微信的异步通知\r\n";
          $message .= '订单号：' . $verify['out_trade_no'] . "\r\n";
          $message .= '订单金额：' . $verify['total_fee'] . "\r\n\r\n";
          hunter_set_message($message);

        //验证通过后, 更新支付记录状态
        db_update('pay_record')
          ->fields(array(
            'status' => 1,
            'updated' => time(),
          ))
          ->condition('out_trade_no', $verify['out_trade_no'])
          ->execute();

          //验证通过后,如果商品类型是会员续费，则添加用户角色及有效期, 请自行修改逻辑
          // if($record->product_type == 'xufei'){
          //   $adddays = $record->month_num;
          //   $uid = $record->uid;
          //   $account = get_user_byid($uid);
          //   $account->role_start_time = date('Y-m-d\T00:00:00', time());
          //   $account->role_end_time = date('Y-m-d\T00:00:00', strtotime("+$adddays day"));
          //   $account->role = $record->role_type;
          //   $account->save();
          // }

          return 'success';
        }
        return 'fail';
      } else {
        return 'fail';
      }
  }

  /**
   * PayConfigForm.
   *
   * @return string
   *   Return PayConfigForm string.
   */
  public function PayConfigForm(ServerRequest $request) {
    $form['alipay_setting'] = [
      '#type' => 'fieldset',
      '#title' => t('Alipay Setting'),
    ];

    $form['alipay_app_id'] = array(
      '#type' => 'textfield',
      '#title' => t('App ID'),
      '#default_value' => $this->pay_config['alipay']['app_id'],
    );

    $form['alipay_notify_url'] = array(
      '#type' => 'textfield',
      '#title' => t('Notify Url'),
      '#default_value' => $this->pay_config['alipay']['notify_url'],
    );

    $form['alipay_return_url'] = array(
      '#type' => 'textfield',
      '#title' => t('Return Url'),
      '#default_value' => $this->pay_config['alipay']['return_url'],
    );

    $form['alipay_ali_public_key'] = [
      '#type' => 'textarea',
      '#title' => t('Public Key'),
      '#rows' => '5',
      '#default_value' => $this->pay_config['alipay']['ali_public_key'],
    ];

    $form['alipay_private_key'] = [
      '#type' => 'textarea',
      '#title' => t('Private Key'),
      '#rows' => '5',
      '#default_value' => $this->pay_config['alipay']['private_key'],
    ];

    $form['wechat_setting'] = [
      '#type' => 'fieldset',
      '#title' => t('Wechat Setting'),
    ];

    $form['wechat_app_id'] = array(
      '#type' => 'textfield',
      '#title' => t('App ID'),
      '#description' => t('微信公众号APPID.'),
      '#default_value' => $this->pay_config['wechat']['app_id'],
    );

    $form['wechat_mch_id'] = array(
      '#type' => 'textfield',
      '#title' => t('Mch ID'),
      '#description' => t('微信商户号.'),
      '#default_value' => $this->pay_config['wechat']['mch_id'],
    );

    $form['wechat_notify_url'] = array(
      '#type' => 'textfield',
      '#title' => t('Notify Url'),
      '#default_value' => $this->pay_config['wechat']['notify_url'],
    );

    $form['wechat_key'] = [
      '#type' => 'textarea',
      '#title' => t('Public Key'),
      '#rows' => '5',
      '#description' => t('微信支付签名秘钥.'),
      '#default_value' => $this->pay_config['wechat']['key'],
    ];

    $form['save'] = array(
     '#type' => 'submit',
     '#value' => t('Save'),
     '#attributes' => array('lay-submit' => '', 'lay-filter' => 'configSubmit'),
    );

    return view('/admin/pay-config.html', array('form' => $form));
  }

  /**
   * PayConfigFormSubmit.
   *
   * @return string
   *   Return PayConfigFormSubmit string.
   */
  public function PayConfigFormSubmit(ServerRequest $request) {
    if ($values = $request->getParsedBody()) {
      $config = config('pay.pay');
      $config->set('pay_config.alipay.app_id', $values['alipay_app_id']);
      $config->set('pay_config.alipay.notify_url', $values['alipay_notify_url']);
      $config->set('pay_config.alipay.return_url', $values['alipay_return_url']);
      $config->set('pay_config.alipay.ali_public_key', $values['alipay_ali_public_key']);
      $config->set('pay_config.alipay.private_key', $values['alipay_private_key']);
      $config->set('pay_config.wechat.app_id', $values['wechat_app_id']);
      $config->set('pay_config.wechat.mch_id', $values['wechat_mch_id']);
      $config->set('pay_config.wechat.notify_url', $values['wechat_notify_url']);
      $config->set('pay_config.wechat.key', $values['wechat_key']);
      $config->save();
      return true;
    }
  }

}
