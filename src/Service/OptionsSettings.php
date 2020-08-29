<?php
namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

class OptionsSettings
{
    /** @var \PDO */
    private $db;
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->db = $this->em->getConnection();
    }

    /**
     * Gets a setting value. 
     * If the setting doesn't exist, returns the default value specified as second param
     */
    public function get(string $name, $default=''): string
    {
            $stmt = $this->db->prepare("SELECT `value` FROM `settings` WHERE `name`=?;");
            $stmt->execute([$name]);
            return $stmt->fetchColumn() ?: $default;
    }

    /**
     * Sets a setting value. 
     * If the setting doesn't exists, it creates it. Otherwise, it replaces the db value
     */
    public function set(string $name, string $value)
    {
        $this->db->prepare("INSERT INTO settings (`name`, `value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=?;")
            ->execute([$name, $value, $value]);
    }
}