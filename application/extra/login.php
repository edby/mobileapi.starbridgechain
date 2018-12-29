<?php

/**
 * 登录控制过滤
 */
return [
    'filter' => [

        /*
         * 运维后台接口
         * 所有字母小写
         * */

		'wallet/upload/upload',



		//SDT记录
        'wallet/admin/getsdtdata', //交易记录
        'wallet/admin/getdata', //sdt个人数据统计
        'wallet/admin/getdataout', //sdt个人数据统计

        //btc
        'wallet/admin/getbtcdata', //BTC数据汇总
        'wallet/admin/gettotaldata', //用户数据btc和sdt汇总
        'wallet/admin/getroutepruse', //获取路由器到钱包的总额

        'wallet/admin/editgroup', //修改用户所属组
        'wallet/admin/editgroupname', //修改组名称
        'wallet/admin/addgroup', //添加组
        'wallet/admin/getgroup', //获取组

        'wallet/admin/switchpurse', //钱包转账功能开启关闭
        'wallet/admin/getstatus',   //获取当前钱包转账状态
        'wallet/admin/out',         //获取全部订单(不分页,excel下载)

        'wallet/admin/spuerwallet',
        'wallet/admin/postinter',


		'wallet/admin/updatebtc',	//修改分红btc基数
		'wallet/admin/updatesdtbase',	//修改分红btc基数
		'wallet/admin/updatefloat',	/*修改页面显示分红上浮下午比例*/
		'wallet/admin/addequipmentnum',
		'wallet/admin/updatenewbtc',
		'wallet/admin/updatenewfloat',

        /****************************************************************/
        'wallet/allocation/regist',	//创建虚拟账户和钱包地址以及sdt
        'wallet/allocation/uuid',	//绑定uuid
        'wallet/allocation/tradenum',	//获取个人每日交易额
        'wallet/allocation/transfer',	//获取每天转账
        'wallet/allocation/btcanalyze',	//btc分红计算
        'wallet/allocation/calculation',	//btc分红计算
//        'wallet/allocation/bindbtcaddr',	//分配btc转账地址
        'wallet/allocation/addsdt',	//虚增sdt

		'wallet/allocation/cal',	//页面
//		'wallet/allocation/locksdt',	//锁仓
		'wallet/allocation/editlocktime',	//锁仓列表
		'wallet/allocation/displayrankings',	//分红历史

		'wallet/allocation/dividend',	//SDT分红

		/*处理余额不对*/
		'wallet/allocation/updatesdt',
		/*获取锁仓数据*/
		'wallet/allocation/selectlock',

		//每天获取11:50分获取今日待分配(today_accumulated)的数量,插入到(today_accumulated_bak)字段
		'wallet/allocation/edittodayacc',
		'wallet/allocation/prebtcanalyze',	//btc分红预备计算
		'wallet/allocation/stopbtcanalyze',	//停止btc分红


		'wallet/allocation/latestdeal',		//最新成交显示
		'wallet/allocation/sendexpirelock',		//最新成交显示
		'wallet/allocation/yesranking',		//获取昨日SDT和BTC排行

		'wallet/allocation/delhour',		//定时删除快照数据



//		'wallet/allocation/locklist',	//锁仓列表
		'wallet/allocation/unlocksdt',	//每小时查看解仓解仓
//		'wallet/allocation/rankings',	//分红历史--测试使用
//		'wallet/trade/putbtc2wallet',//BTC提现到公网钱包--测试使用
//		'wallet/user/getmybalance',	//获取记录--测试使用
//		'wallet/trade/buybtc',//购买btc--测试使用

/********************  一键提取    **********************************/
		//获取汇率
		'wallet/extract/getexchangerate',
		/*发送绑定验证码*/
//		'wallet/extract/sendbindcode',
		/*绑定钱包和手机号*/
//		'wallet/extract/bindmobile',

		/*手机号查余额*/
//		'wallet/extract/balancemobile',

		/*发送提取验证码*/
//		'wallet/extract/sendextractcode',

		/*提取余额*/
//		'wallet/extract/extractbalance',

		/*删除钱包*/
//		'wallet/extract/deletepurse',

		/*删除账号绑定*/
//		'wallet/extract/deletemobile',

		/*获取钱包列表*/
//		'wallet/purse/getpurse',		//测试使用

/*************************新矿池入驻 特惠新用户******************************************/
		/*sn和绑定*/
//		'wallet/newcheckin/bindsn',
		'wallet/newcheckin/showdividend',
		'wallet/newcheckin/caldividend',
		'wallet/newcheckin/gettime',
		'wallet/newcheckin/addbatch',
		'wallet/newcheckin/getbatch',
		'wallet/newcheckin/getbatchlist',
		'wallet/newcheckin/btcdividend',
//		'wallet/newcheckin/getinfo',
		'wallet/newcheckin/showadd',
		'wallet/newcheckin/pushaccount',
		'wallet/newcheckin/login',
//		'wallet/newcheckin/bindingsn',



/****************************************************************/

        'wallet/user/login',       //登录
        'wallet/user/reg',         //注册
        'wallet/user/retrievepsd', //找回密码
        'wallet/email/sendreg',    //注册时发送验证码
        'wallet/email/sendret',    //找回密码时发送验证码
        'wallet/route/binduser',   //绑定路由app
        'wallet/route/bindwallet',  //路由绑定钱包
        'wallet/route/putsbc',      //提现
        'wallet/route/untiewallet', //解绑
        //订单相关
        'wallet/order/getlistall', //获取全部订单
        'wallet/order/out', //获取全部订单(不分页,excel下载)
        'wallet/order/changestatus', //修改订单状态

        //钱包转账功能开启关闭
        'wallet/purse/switchpurse',
        'wallet/purse/getstatus',



        //协议相关
        'wallet/agreement/getagreement',//获取协议
        'wallet/agreement/getagreementall',//获取全部协议
        'wallet/agreement/editagreement',//修改协议
        //sbc价格
        'wallet/price/getlist', //获取价格列表
        'wallet/price/editprice',//修改价格
        //手续费
        'wallet/fee/getfeeall',//全部手续费
        'wallet/fee/editfee',//修改手续费

        //配置
        'wallet/config/getconfig',
        'wallet/config/editconfig',


        //后台脚本
        'wallet/script/synchronization',

        //-------------------
        'wallet/miss/miss',        //miss路由
        'wallet/index/test',        //测试
//        'wallet/purse/wallettransfer',        //
        'wallet/trade/getbalance',        //
        'wallet/trade/setincsdt',        //
        'wallet/order/authrecharge',        //
        'wallet/order/authorder',        //
        'wallet/order/authout',        //
        'wallet/index/getversion',        //
        'wallet/index/getios',        //
        'wallet/index/aaa',        //
        'wallet/public/synchronizehour',        //
        'wallet/upload/downloads',        //
        'wallet/public/gettransferloglist',


        /****************************************************************/
        //星桥链官网 首页接口白名单
        'wallet/notice/index',

        'wallet/notice/buildspreadcode',       //脚本 执行写入 没有推广码的 数据
        'wallet/email/sendrec',                    //发送邮件前获取reg_code

        'wallet/email/sendreg_y',                    //发送验证码到玉庒邮箱

        'wallet/email/startcaptcha',                 //首次发送验证参数 极验

        'wallet/email/check',                        //app请求验证邮箱

        'wallet/ad/getad',                          //获取广告接口
        'wallet/ad/clickad',                        //点击广告接口

        'wallet/notice/firstnotice',                //置顶公告
        'wallet/notice/listnotice',                 //公告列表
    ]
];
