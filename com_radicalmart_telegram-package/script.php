<?php
defined('_JEXEC') or die;

class pkg_radicalmart_telegramInstallerScript
{
    public function install($parent)
    {
        // Code to run during the installation process
        // For example, creating database tables or setting default configurations
    }

    public function uninstall($parent)
    {
        // Code to run during the uninstallation process
        // For example, dropping database tables or cleaning up configurations
    }

    public function update($parent)
    {
        // Code to run during the update process
        // For example, migrating data or updating configurations
    }

    public function preflight($type, $parent)
    {
        // Code to run before installation, uninstallation, or update
        // For example, checking for dependencies or permissions
    }

    public function postflight($type, $parent)
    {
        // Code to run after installation, uninstallation, or update
        // For example, displaying a message or performing cleanup tasks
    }
}
