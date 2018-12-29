<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

//return [
//    '__pattern__' => [
//        'name' => '\w+',
//    ],
//    '[hello]'     => [
//        ':id'   => ['index/hello', ['method' => 'get'], ['id' => '\d+']],
//        ':name' => ['index/hello', ['method' => 'post']],
//    ],
//
//];
use \think\Route;

Route::group('wallet',function(){



    /*
     * 运维后台
     * */
    Route::group('admin',function(){


        Route::post('switchPurse','wallet/admin/switchPurse');//钱包转账功能是否开启
        Route::post('getStatus','wallet/admin/getStatus');//获取当前钱包转账状态
        Route::post('out','wallet/admin/out');//获取全部订单(不分页,excel下载)
        //sdt
        Route::post('getSdtData','wallet/admin/getSdtData');//获取SDT交易记录
        Route::post('getData','wallet/admin/getData');//获取SDT用户数据总览
        Route::post('getDataOut','wallet/admin/getDataOut');//获取SDT用户数据excel下载



        //btc
        Route::post('getBtcData','wallet/admin/getBtcData');//btc数据总览

        //btc和sdt数据汇总
        Route::post('getTotalData','wallet/admin/getTotalData');

        //获取路由提取到钱包的总额
        Route::post('getRoutePruse','wallet/admin/getRoutePruse');


        //修改用户钱包所属组
        Route::post('editGroup','wallet/admin/editGroup');
        Route::post('editGroupName','wallet/admin/editGroupName');  //修改组名称
        Route::post('addGroup','wallet/admin/addGroup');  //添加组
        Route::post('getGroup','wallet/admin/getGroup');  //获取组
        //创建对公钱包
        Route::post('spuerWallet','wallet/admin/spuerWallet');
        //转账第三方接口
        Route::post('postInter','wallet/admin/postInter');


        Route::post('updateBtc','wallet/admin/updateBtc');  /*修改btc分红基数*/
        Route::post('updateSdtBase','wallet/admin/updateSdtBase');  /*修改sdt分红基数*/
        Route::post('updateFloat','wallet/admin/updateFloat'); /*修改页面显示分红上浮下午比例*/
        Route::any('addEquipmentNum','wallet/admin/addEquipmentNum');
        Route::post('updateNewBtc','wallet/admin/updateNewBtc');
        Route::post('updateNewFloat','wallet/admin/updateNewFloat');

    });



    /*
     *分红
     * */
    Route::group('allocation',function(){

        Route::post('regist','wallet/allocation/regist');
        Route::post('uuid','wallet/allocation/uuid');
        Route::post('tradeNum','wallet/allocation/tradeNum');
        Route::get('transfer','wallet/allocation/transfer'); //获取每天转账
        Route::get('btcAnalyze','wallet/allocation/btcAnalyze'); //btc分红
        Route::post('calculation','wallet/allocation/calculation'); //页面计算
        Route::post('bindBtcAddr','wallet/allocation/bindBtcAddr'); //分配btc转账地址
        Route::get('addSdt','wallet/allocation/addSdt'); //虚增sdt
        Route::post('getAllocationList','wallet/allocation/getAllocationList'); //分红记录
        Route::get('cal','wallet/allocation/cal');     //页面计算

        Route::post('lockSdt','wallet/allocation/lockSdt'); //锁仓
        Route::get('unlockSdt','wallet/allocation/unlockSdt'); //解仓
        Route::post('lockList','wallet/allocation/lockList'); //锁仓列表记录
        Route::post('editLockTime','wallet/allocation/editLockTime'); //修改锁仓续期状态
        Route::get('rankings','wallet/allocation/rankings'); //分红排行
        Route::post('displayRankings','wallet/allocation/displayRankings'); //分红排行显示

        Route::get('dividend','wallet/allocation/dividend');    //SDT分红

        Route::get('updateSdt','wallet/allocation/updateSdt'); /*处理余额*/
        Route::get('selectLock','wallet/allocation/selectLock');/*获取锁仓数据*/

        /*每天获取11:50分获取今日待分配(today_accumulated)的数量,插入到(today_accumulated_bak)字段*/
        Route::get('editTodayAcc','wallet/allocation/editTodayAcc');

        Route::get('preBtcAnalyze','wallet/allocation/preBtcAnalyze'); /*预备btc分红*/
        Route::get('stopBtcAnalyze','wallet/allocation/stopBtcAnalyze'); /*停止btc分红*/


        Route::get('yesRanking','wallet/allocation/yesRanking'); /*获取昨日SDT和BTC排行*/
        Route::get('latestDeal','wallet/allocation/latestDeal'); /*最新成交显示*/
        Route::get('sendExpireLock','wallet/allocation/sendExpireLock'); /*提前两天给锁仓到期的账户发提醒邮件*/

        Route::get('delHour','wallet/allocation/delHour'); /*定时删除快照数据*/

    });


    /*
     * 一键提取
     * */
    Route::group('extract',function(){

        Route::post('sendBindCode','wallet/extract/sendBindCode');
        Route::post('bindMobile','wallet/extract/bindMobile');
        Route::post('balanceMobile','wallet/extract/balanceMobile');
        Route::post('sendExtractCode','wallet/extract/sendExtractCode');
        Route::post('extractBalance','wallet/extract/extractBalance');
        Route::post('deletePurse','wallet/extract/deletePurse');
        Route::post('deleteMobile','wallet/extract/deleteMobile');
        Route::get('getExchangeRate','wallet/extract/getExchangeRate');  //获取汇率


    });

    /*
     * 新矿池入驻 特惠新用户
     * */
    Route::group('newcheckin',function(){

        Route::post('bindSn','wallet/newcheckin/bindSn');
        Route::post('showDividend','wallet/newcheckin/showDividend');
        Route::get('calDividend','wallet/newcheckin/calDividend');
        Route::post('getTime','wallet/newcheckin/getTime');
        Route::post('addBatch','wallet/newcheckin/addBatch');
        Route::post('getBatch','wallet/newcheckin/getBatch');
        Route::post('getBatchList','wallet/newcheckin/getBatchList');
        Route::get('btcDividend','wallet/newcheckin/btcDividend');
        Route::post('getInfo','wallet/newcheckin/getInfo');
        Route::post('showAdd','wallet/newcheckin/showAdd');
        Route::get('pushAccount','wallet/newcheckin/pushAccount');
        Route::post('login','wallet/newcheckin/login');
        Route::post('bindingSn','wallet/newcheckin/bindingSn');



    });


    /**
     * 会员
     */
    Route::group('user',function(){
        //注册
        Route::post('reg','wallet/user/reg');
        //找回密码
        Route::post('retrievePsd','wallet/user/retrievePsd');
        //登录
        Route::post('login','wallet/user/login');
        //登录成功发送邮件
        Route::post('loginSendEmail','wallet/user/loginSendEmail');
        //修改密码
        Route::post('changePsd','wallet/user/changePsd');
        //退出
        Route::post('logout','wallet/user/logout');
        //获取fbtc或cny的余额
        Route::post('getBalance','wallet/user/getBalance');
        //获取全部币种的余额
        Route::post('getAllBalance','wallet/user/getAllBalance');
        //创建钱包
        Route::post('createWallet','wallet/user/createWallet');
        //获取sdt/btc余额
        Route::post('getMyBalance','wallet/user/getMyBalance');
    });
    /**
     * 钱包
     */
    Route::group('purse',function(){
        //获取用户钱包列表
        Route::post('getPurse','wallet/purse/getPurse');
        Route::post('walletTransfer','wallet/purse/walletTransfer');

        //钱包转账功能是否开启
        Route::post('switchPurse','wallet/purse/switchPurse');
        //获取当前钱包转账状态
        Route::post('getStatus','wallet/purse/getStatus');
    });
    /**
     * 图片上传
     */
    Route::group('upload',function(){
        Route::post('upload','wallet/Upload/upload');
        Route::any('downloads/:package','wallet/Upload/downloads');
    });
    /**
     * 邮箱
     */
    Route::group('email',function(){
        Route::post('sendReg','wallet/Email/sendReg');//注册时验证码
        Route::post('sendRet','wallet/Email/sendRet');//找回密码时验证码
        Route::post('sendRec','wallet/Email/sendRec');//注册发送code
    });

    /**
     * 路由app接口
     */
    Route::group('route',function(){
        Route::post('bindUser','wallet/Route/bindUser');//绑定路由app
        Route::post('bindWallet','wallet/Route/bindWallet');//路由绑定钱包
        Route::post('putSBC','wallet/Route/putSBC');//提现
        Route::post('untieWallet','wallet/Route/untieWallet');//解绑路由器-钱包
    });

    /**
     * 交易（充值，提现等）
     */
    Route::group('trade',function(){
        Route::post('getSBCPrice','wallet/Trade/getSBCPrice');//获取SBC价格
        Route::post('buySBC','wallet/Trade/buySBC');//SBC购买
        Route::post('buyCNY','wallet/Trade/buyCNY');//CNY充值
        Route::post('putCNY','wallet/Trade/putCNY');//CNY提现
        Route::post('buyFBTC','wallet/Trade/buyFBTC');//FBTC充值
        Route::post('putFBTC','wallet/Trade/putFBTC');//FBTC提现
        Route::post('putBTC2Wallet','wallet/Trade/putBTC2Wallet');//BTC提现到公网钱包
        Route::post('putBTC2FBTC','wallet/Trade/putBTC2FBTC');//BTC提现到FBTC
        Route::post('buyBTC','wallet/Trade/buyBTC');//购买btc
        Route::post('getBalance','wallet/Trade/getBalance');//购买btc
        Route::post('setIncsdt','wallet/Trade/setIncsdt');//购买btc
    });

    /**
     * 订单
     */
    Route::group('order',function(){
            Route::post('getListAll','wallet/order/getListAll');//获取全部订单
            Route::post('out','wallet/order/out');//获取全部订单(不分页,excel下载)

            Route::post('getList','wallet/order/getList');//获取用户订单(旧版本)
            Route::post('newgetList','wallet/order/newgetList');//获取用户订单(新版本)
            Route::post('changeStatus','wallet/order/changeStatus');//修改订单状态
            Route::any('getSdtTransferLogList','wallet/order/getSdtTransferLogList');//获取sdt转账记录(旧版本)
            Route::any('newgetSdtTransferLogList','wallet/order/newgetSdtTransferLogList');//获取sdt转账记录(新版本)
            Route::any('withdrawOrder','wallet/order/withdrawOrder');//获取sdt转账记录
            Route::any('authOrder','wallet/order/authOrder');//撤回审核
            Route::any('authRecharge','wallet/order/authRecharge');//充值审核
            Route::any('authOut','wallet/order/authOut');//提现审核
    });

    /**
     * 用户协议
     */
    Route::group('agreement',function(){
        Route::post('getAgreement','wallet/agreement/getAgreement');//获取用户协议
        Route::post('getAgreementAll','wallet/agreement/getAgreementAll');//获取全部用户协议
        Route::post('editAgreement','wallet/agreement/editAgreement');//修改用户协议
    });

    /**
     * sbc价格
     */
    Route::group('price',function(){
       Route::post('getList','wallet/price/getList');//SBC价格
//       Route::post('editPrice','wallet/price/editPrice');//修改SBC价格
    });

    /**
     * 手续费
     */
    Route::group('fee',function(){
        Route::post('getFee','wallet/fee/getFee');//获取手续费
        Route::post('getFeeAll','wallet/fee/getFeeAll');//获取全部手续费
//        Route::post('editFee','wallet/fee/editFee');//修改手续费
    });

    /**
     * 配置
     */
    Route::group('config',function(){
        Route::post('getWalletAdr','wallet/config/getWalletAdr'); //获取官方钱包地址
        Route::post('getConfig','wallet/config/getConfig');//获取全部配置
//        Route::post('editConfig','wallet/config/editConfig');//修改配置
    });
    /**
     * 后台脚本
     */
    Route::group('script',function(){
        Route::get('synchronization','wallet/script/synchronization');//同步SBC价格
    });

    Route::group('public',function(){
        Route::post('getTransferLogList','wallet/Public/getTransferLogList');//同步SBC价格
        Route::any('SynchronizeHour','wallet/Public/SynchronizeHour');
    });

    //测试
    Route::group('index',function (){
        Route::any('aaa','wallet/Index/aaa');
        Route::any('getversion','wallet/Index/getversion');
        Route::any('getios','wallet/Index/getios');
    });

    //公告
    Route::group('notice',function (){
        Route::any('index','wallet/Notice/index');
        Route::any('help','wallet/Notice/help');
        Route::any('question','wallet/Notice/question');


        //手动生成 已有数据的推广码!
        Route::get('buildSpreadCode','wallet/Notice/buildSpreadCode');
        //获取推广数据
        Route::post('spreadInfo','wallet/Notice/spreadInfo');
        //获取二级推广列表
        Route::post('spreadInfoSec','wallet/Notice/spreadInfoSec');

        //获取排行数据 - 统计信息
        Route::post('rankingInfo','wallet/Notice/rankingInfo');
    });
    
});

Route::miss('wallet/Miss/miss');//miss

Route::get('sendReg_y/:email','wallet/Email/sendReg_y');//注册发送code

Route::get('startCaptcha','wallet/Email/startCaptcha');//首次发送验证参数 极验

Route::post('check','wallet/Email/check');//app请求验证邮箱

Route::post('getAd','wallet/Ad/getAd');//获取广告接口

Route::post('clickAd','wallet/Ad/clickAd');//点击广告接口

//公告相关接口
Route::post('firstNotice','wallet/notice/firstNotice');
Route::post('listNotice','wallet/notice/listNotice');