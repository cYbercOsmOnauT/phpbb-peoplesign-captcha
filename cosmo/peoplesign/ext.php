<?php
/**
 *
 * @author    Tekin Birdüzen <t.birduezen@web-coding.eu>
 * @since     26.05.15
 * @version   1.0.1
 * @copyright Tekin Birdüzen
 * @package   phpBB Extension
 * @license   http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 */

namespace cosmo\peoplesign;

/**
 * @ignore
 */

class ext extends \phpbb\extension\base
{
	private $config;
	private static $ps_opt_num_reg = 4;

	/**
	 * Single disable step
	 *
	 * @param mixed $old_state State returned by previous call of this method
	 *
	 * @return mixed Returns false after last step, otherwise temporary state
	 */
	public function disable_step($old_state)
	{
		switch ($old_state)
		{
			case '': // Empty means nothing has run yet
				$this->getconfig();
				// Check if peoplesign currently is the default captcha
				if ($this->config['captcha_plugin'] === $this->container->get('cosmo.peoplesign.captcha.peoplesign')->get_service_name())
				{
					// It's the default captcha, set the default captcha to phpBB's default GD captcha.
					$this->config->set('captcha_plugin', 'core.captcha.plugins.gd');
				}
				return 'default_captcha_changed';

				break;

			default:
				// Run parent disable step method
				return parent::disable_step($old_state);

				break;
		}
	}

	/**
	 * Overwrite purge_step to purge notifications before
	 * any included and installed migrations are reverted.
	 *
	 * @param mixed $old_state State returned by previous call of this method
	 *
	 * @return mixed Returns false after last step, otherwise temporary state
	 */
	public function purge_step($old_state)
	{
		switch ($old_state)
		{
			case '': // Empty means nothing has run yet
				$this->getconfig();
				// Purge Config vars
				foreach (self::get_peoplesign_confignames() as $confname)
				{
					$this->config->delete($confname);
				}
				return 'deleted';
				break;

			default:
				// Run parent purge step method
				return parent::purge_step($old_state);

				break;
		}
	}

	private function getconfig()
	{
		// Get config
		$this->config = $this->container->get('config');
	}

	private static function get_peoplesign_confignames()
	{
		$names = array('peoplesign_key');
		for ($i = 0; $i < self::$ps_opt_num_reg; $i++)
		{
			$names[] = 'peoplesign_options' . $i;
		}
		return $names;
	}
}
