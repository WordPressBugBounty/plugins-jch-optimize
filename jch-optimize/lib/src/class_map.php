<?php

namespace _JchOptimizeVendor;

/*
 * JCH Optimize - Performs several front-end optimizations for fast downloads
 *
 * @package   jchoptimize/core
 * @author    Samuel Marshall <samuel@jch-optimize.net>
 * @copyright Copyright (c) 2023 Samuel Marshall / JCH Optimize
 * @license   GNU/GPLv3, or later. See LICENSE file
 *
 *  If LICENSE file missing, see <http://www.gnu.org/licenses/>.
 */
use _JchOptimizeVendor\GuzzleHttp\Exception\GuzzleException;
use _JchOptimizeVendor\GuzzleHttp\Exception\RequestException;
use _JchOptimizeVendor\GuzzleHttp\Psr7\UploadedFile;
use _JchOptimizeVendor\GuzzleHttp\Psr7\Uri;
use _JchOptimizeVendor\GuzzleHttp\RequestOptions;
use _JchOptimizeVendor\Joomla\DI\Container;
use _JchOptimizeVendor\Joomla\DI\ContainerAwareInterface;
use _JchOptimizeVendor\Joomla\DI\ServiceProviderInterface;
use _JchOptimizeVendor\Joomla\Filesystem\File;
use _JchOptimizeVendor\Joomla\Filesystem\Folder;
use _JchOptimizeVendor\Joomla\Input\Input;
use _JchOptimizeVendor\Psr\Container\ContainerInterface;
use _JchOptimizeVendor\Psr\Http\Message\ResponseInterface;
use _JchOptimizeVendor\Psr\Http\Message\UriInterface;
use _JchOptimizeVendor\Psr\Log\AbstractLogger;
use _JchOptimizeVendor\Psr\Log\LoggerAwareInterface;
use _JchOptimizeVendor\Psr\Log\LoggerAwareTrait;
use _JchOptimizeVendor\Psr\Log\LoggerInterface;
use _JchOptimizeVendor\Psr\Log\LogLevel;
use _JchOptimizeVendor\Spatie\Crawler\CrawlObservers\CrawlObserver;

// Uri
if (!\class_exists('\\JchOptimize\\Core\\Uri\\Uri', \false)) {
    \class_alias(Uri::class, '\\JchOptimize\\Core\\Uri\\Uri');
}
if (!\class_exists('\\JchOptimize\\Core\\Uri\\RequestOptions', \false)) {
    \class_alias(RequestOptions::class, '\\JchOptimize\\Core\\Uri\\RequestOptions');
}
if (!\interface_exists('\\JchOptimize\\Core\\Uri\\ResponseInterface', \false)) {
    \class_alias(ResponseInterface::class, '\\JchOptimize\\Core\\Uri\\ResponseInterface');
}
if (!\interface_exists('\\JchOptimize\\Psr\\Uri\\UriInterface', \false)) {
    \class_alias(UriInterface::class, '\\JchOptimize\\Psr\\Uri\\UriInterface');
}
if (!\class_exists('\\JchOptimize\\Core\\Uri\\UploadedFile', \false)) {
    \class_alias(UploadedFile::class, '\\JchOptimize\\Core\\Uri\\UploadedFile');
}
// Container
if (!\class_exists('\\JchOptimize\\Core\\Container\\Container', \false)) {
    \class_alias(Container::class, '\\JchOptimize\\Core\\Container\\Container');
}
if (!\interface_exists('\\JchOptimize\\Core\\Container\\ContainerAwareInterface', \false)) {
    \class_alias(ContainerAwareInterface::class, '\\JchOptimize\\Core\\Container\\ContainerAwareInterface');
}
if (!\interface_exists('\\JchOptimize\\Core\\Container\\ServiceProviderInterface', \false)) {
    \class_alias(ServiceProviderInterface::class, '\\JchOptimize\\Core\\Container\\ServiceProviderInterface');
}
// Psr
if (!\interface_exists('\\JchOptimize\\Core\\Psr\\Container\\ContainerInterface', \false)) {
    \class_alias(ContainerInterface::class, '\\JchOptimize\\Core\\Psr\\Container\\ContainerInterface');
}
if (!\interface_exists('\\JchOptimize\\Core\\Psr\\Log\\LoggerInterface', \false)) {
    \class_alias(LoggerInterface::class, '\\JchOptimize\\Core\\Psr\\Log\\LoggerInterface');
}
if (!\interface_exists('\\JchOptimize\\Core\\Psr\\Log\\LoggerAwareInterface', \false)) {
    \class_alias(LoggerAwareInterface::class, '\\JchOptimize\\Core\\Psr\\Log\\LoggerAwareInterface');
}
if (!\trait_exists('\\JchOptimize\\Core\\Psr\\Log\\LoggerAwareTrait', \false)) {
    \class_alias(LoggerAwareTrait::class, '\\JchOptimize\\Core\\Psr\\Log\\LoggerAwareTrait');
}
if (!\class_exists('\\JchOptimize\\Core\\Psr\\Log\\LogLevel', \false)) {
    \class_alias(LogLevel::class, '\\JchOptimize\\Core\\Psr\\Log\\LogLevel');
}
if (!\class_exists('\\JchOptimize\\Core\\Psr\\Log\\AbstractLogger', \false)) {
    \class_alias(AbstractLogger::class, '\\JchOptimize\\Core\\Psr\\Log\\AbstractLogger');
}
// Filesystem
if (!\class_exists('\\JchOptimize\\Core\\Filesystem\\File', \false)) {
    \class_alias(File::class, '\\JchOptimize\\Core\\Filesystem\\File');
}
if (!\class_exists('\\JchOptimize\\Core\\Filesystem\\Folder', \false)) {
    \class_alias(Folder::class, '\\JchOptimize\\Core\\Filesystem\\Folder');
}
// Exception
if (!\class_exists('\\JchOptimize\\Core\\Exception\\RequestException', \false)) {
    \class_alias(RequestException::class, '\\JchOptimize\\Core\\Exception\\RequestException');
}
if (!\interface_exists('\\JchOptimize\\Core\\Exception\\GuzzleException', \false)) {
    \class_alias(GuzzleException::class, '\\JchOptimize\\Core\\Exception\\GuzzleException');
}
// Input
if (!\class_exists('\\JchOptimize\\Core\\Input', \false)) {
    \class_alias(Input::class, '\\JchOptimize\\Core\\Input');
}
if (\JCH_PRO) {
    // Spatie
    if (!\class_exists('\\JchOptimize\\Core\\Spatie\\CrawlObserver', \false)) {
        \class_alias(CrawlObserver::class, '\\JchOptimize\\Core\\Spatie\\CrawlObserver');
    }
}
