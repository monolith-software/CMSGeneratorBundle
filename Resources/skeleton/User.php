<?php

namespace App;

use Doctrine\ORM\Mapping as ORM;
use Monolith\Bundle\CMSBundle\Model\UserModel;

/**
 * @ORM\Entity
 * @ORM\Table(name="users")
 */
class User extends UserModel
{
}
