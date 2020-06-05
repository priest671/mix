<?php

namespace Mix\Process;

use Mix\Coroutine\Coroutine;

/**
 * Class Process
 * @package Mix\Process
 */
class Process
{

    /**
     * 使当前进程蜕变为一个守护进程
     * @param bool $noclose
     * @param bool $nochdir
     * @return bool
     */
    public static function daemon(bool $noclose = false, bool $nochdir = true)
    {
        if (static::isMac() && \Swoole\Coroutine::getCid() > -1) {
            throw new \Swoole\Exception('MacOS unsupport fork in coroutine, please use it before the Swoole\Coroutine\Scheduler start.');
        }
        return \Swoole\Process::daemon($nochdir, $noclose);
    }

    /**
     * 设置进程标题
     * @param string $title
     * @return bool
     */
    public static function setProcessTitle(string $title)
    {
        if (static::isMac() || static::isWin()) {
            return false;
        }
        if (!function_exists('cli_set_process_title')) {
            return false;
        }
        return @cli_set_process_title($title);
    }

    /**
     * kill进程
     * @param int $pid
     * @param int $signal
     * @return bool
     */
    public static function kill(int $pid, int $signal = SIGTERM)
    {
        return posix_kill($pid, $signal);
    }

    /**
     * 返回当前进程ID
     * @return int
     */
    public static function getPid()
    {
        return getmypid();
    }

    /**
     * 批量设置异步信号监听
     * @param array $signals
     * @param callable|null $callback
     * @param bool $enableCoroutine
     */
    public static function signal(array $signals, $callback, bool $enableCoroutine = true)
    {
        foreach ($signals as $signal) {
            if (is_null($callback)) {
                \Swoole\Process::signal($signal, null);
                continue;
            }
            \Swoole\Process::signal($signal, function ($signal) use ($callback, $enableCoroutine) {
                if ($enableCoroutine) {
                    // 创建协程
                    Coroutine::create($callback, $signal);
                } else {
                    try {
                        // 执行闭包
                        call_user_func($callback, $signal);
                    } catch (\Throwable $e) {
                        $isMix = class_exists(\Mix::class);
                        // 错误处理
                        if (!$isMix) {
                            throw $e;
                        }
                        // Mix错误处理
                        /** @var \Mix\Console\Error $error */
                        $error = \Mix::$app->context->get('error');
                        $error->handleException($e);
                    }
                }
            });
        }
    }

    /**
     * 是否为 Win 系统
     * @return bool
     */
    protected static function isWin()
    {
        if (static::isMac()) {
            return false;
        }
        return stripos(PHP_OS, 'WIN') !== false;
    }

    /**
     * 是否为 Mac 系统
     * @return bool
     */
    protected static function isMac()
    {
        return stripos(PHP_OS, 'Darwin') !== false;
    }

}
