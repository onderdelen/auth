<?php namespace Einherjars\Carbuncle;
/**
 * Part of the Carbuncle package.
 *
 * NOTICE OF LICENSE
 *
 * Licensed under the 3-clause BSD License.
 *
 * This source file is subject to the 3-clause BSD License that is
 * bundled with this package in the LICENSE file.  It is also available at
 * the following URL: http://www.opensource.org/licenses/BSD-3-Clause
 *
 * @package    Carbuncle
 * @version    2.0.0
 * @author     Einherjars LLC
 * @license    BSD License (3-clause)
 * @copyright  (c) 2011 - 2013, Einherjars LLC
 * @link       http://einherjars.com
 */

use Einherjars\Carbuncle\Cookies\IlluminateCookie;
use Einherjars\Carbuncle\Groups\Eloquent\Provider as GroupProvider;
use Einherjars\Carbuncle\Hashing\BcryptHasher;
use Einherjars\Carbuncle\Hashing\NativeHasher;
use Einherjars\Carbuncle\Hashing\Sha256Hasher;
use Einherjars\Carbuncle\Hashing\WhirlpoolHasher;
use Einherjars\Carbuncle\Carbuncle;
use Einherjars\Carbuncle\Sessions\IlluminateSession;
use Einherjars\Carbuncle\Throttling\Eloquent\Provider as ThrottleProvider;
use Einherjars\Carbuncle\Users\Eloquent\Provider as UserProvider;
use Illuminate\Support\ServiceProvider;

class CarbuncleServiceProvider extends ServiceProvider {

	/**
	 * Boot the service provider.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package('einherjars/carbuncle', 'einherjars/carbuncle');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->registerHasher();
		$this->registerUserProvider();
		$this->registerGroupProvider();
		$this->registerThrottleProvider();
		$this->registerSession();
		$this->registerCookie();
		$this->registerCarbuncle();
	}

	/**
	 * Register the hasher used by Carbuncle.
	 *
	 * @return void
	 */
	protected function registerHasher()
	{
		$this->app['carbuncle.hasher'] = $this->app->share(function($app)
		{
			$hasher = $app['config']['einherjars/carbuncle::hasher'];

			switch ($hasher)
			{
				case 'native':
					return new NativeHasher;
					break;

				case 'bcrypt':
					return new BcryptHasher;
					break;

				case 'sha256':
					return new Sha256Hasher;
					break;

				case 'whirlpool':
					return new WhirlpoolHasher;
					break;
			}

			throw new \InvalidArgumentException("Invalid hasher [$hasher] chosen for Carbuncle.");
		});
	}

	/**
	 * Register the user provider used by Carbuncle.
	 *
	 * @return void
	 */
	protected function registerUserProvider()
	{
		$this->app['carbuncle.user'] = $this->app->share(function($app)
		{
			$model = $app['config']['einherjars/carbuncle::users.model'];

			// We will never be accessing a user in Carbuncle without accessing
			// the user provider first. So, we can lazily set up our user
			// model's login attribute here. If you are manually using the
			// attribute outside of Carbuncle, you will need to ensure you are
			// overriding at runtime.
			if (method_exists($model, 'setLoginAttributeName'))
			{
				$loginAttribute = $app['config']['einherjars/carbuncle::users.login_attribute'];

				forward_static_call_array(
					array($model, 'setLoginAttributeName'),
					array($loginAttribute)
				);
			}

			// Define the Group model to use for relationships.
			if (method_exists($model, 'setGroupModel'))
			{
				$groupModel = $app['config']['einherjars/carbuncle::groups.model'];

				forward_static_call_array(
					array($model, 'setGroupModel'),
					array($groupModel)
				);
			}

			// Define the user group pivot table name to use for relationships.
			if (method_exists($model, 'setUserGroupsPivot'))
			{
				$pivotTable = $app['config']['einherjars/carbuncle::user_groups_pivot_table'];

				forward_static_call_array(
					array($model, 'setUserGroupsPivot'),
					array($pivotTable)
				);
			}

			return new UserProvider($app['carbuncle.hasher'], $model);
		});
	}

	/**
	 * Register the group provider used by Carbuncle.
	 *
	 * @return void
	 */
	protected function registerGroupProvider()
	{
		$this->app['carbuncle.group'] = $this->app->share(function($app)
		{
			$model = $app['config']['einherjars/carbuncle::groups.model'];

			// Define the User model to use for relationships.
			if (method_exists($model, 'setUserModel'))
			{
				$userModel = $app['config']['einherjars/carbuncle::users.model'];

				forward_static_call_array(
					array($model, 'setUserModel'),
					array($userModel)
				);
			}

			// Define the user group pivot table name to use for relationships.
			if (method_exists($model, 'setUserGroupsPivot'))
			{
				$pivotTable = $app['config']['einherjars/carbuncle::user_groups_pivot_table'];

				forward_static_call_array(
					array($model, 'setUserGroupsPivot'),
					array($pivotTable)
				);
			}

			return new GroupProvider($model);
		});
	}

	/**
	 * Register the throttle provider used by Carbuncle.
	 *
	 * @return void
	 */
	protected function registerThrottleProvider()
	{
		$this->app['carbuncle.throttle'] = $this->app->share(function($app)
		{
			$model = $app['config']['einherjars/carbuncle::throttling.model'];

			$throttleProvider = new ThrottleProvider($app['carbuncle.user'], $model);

			if ($app['config']['einherjars/carbuncle::throttling.enabled'] === false)
			{
				$throttleProvider->disable();
			}

			if (method_exists($model, 'setAttemptLimit'))
			{
				$attemptLimit = $app['config']['einherjars/carbuncle::throttling.attempt_limit'];

				forward_static_call_array(
					array($model, 'setAttemptLimit'),
					array($attemptLimit)
				);
			}
			if (method_exists($model, 'setSuspensionTime'))
			{
				$suspensionTime = $app['config']['einherjars/carbuncle::throttling.suspension_time'];

				forward_static_call_array(
					array($model, 'setSuspensionTime'),
					array($suspensionTime)
				);
			}
			
			// Define the User model to use for relationships.
			if (method_exists($model, 'setUserModel'))
			{
				$userModel = $app['config']['einherjars/carbuncle::users.model'];

				forward_static_call_array(
					array($model, 'setUserModel'),
					array($userModel)
				);
			}

			return $throttleProvider;
		});
	}

	/**
	 * Register the session driver used by Carbuncle.
	 *
	 * @return void
	 */
	protected function registerSession()
	{
		$this->app['carbuncle.session'] = $this->app->share(function($app)
		{
			$key = $app['config']['einherjars/carbuncle::cookie.key'];

			return new IlluminateSession($app['session.store'], $key);
		});
	}

	/**
	 * Register the cookie driver used by Carbuncle.
	 *
	 * @return void
	 */
	protected function registerCookie()
	{
		$this->app['carbuncle.cookie'] = $this->app->share(function($app)
		{
			$key = $app['config']['einherjars/carbuncle::cookie.key'];

			/**
			 * We'll default to using the 'request' strategy, but switch to
			 * 'jar' if the Laravel version in use is 4.0.*
			 */

			$strategy = 'request';

			if (preg_match('/^4\.0\.\d*$/D', $app::VERSION))
			{
				$strategy = 'jar';
			}

			return new IlluminateCookie($app['request'], $app['cookie'], $key, $strategy);
		});
	}

	/**
	 * Takes all the components of Carbuncle and glues them
	 * together to create Carbuncle.
	 *
	 * @return void
	 */
	protected function registerCarbuncle()
	{
		$this->app['carbuncle'] = $this->app->share(function($app)
		{
			return new Carbuncle(
				$app['carbuncle.user'],
				$app['carbuncle.group'],
				$app['carbuncle.throttle'],
				$app['carbuncle.session'],
				$app['carbuncle.cookie'],
				$app['request']->getClientIp()
			);
		});

		$this->app->alias('carbuncle', 'Einherjars\Carbuncle\Carbuncle');
	}

}
