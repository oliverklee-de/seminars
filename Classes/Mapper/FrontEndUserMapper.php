<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Mapper;

use OliverKlee\Oelib\Mapper\AbstractDataMapper;
use OliverKlee\Seminars\Model\FrontEndUser;

/**
 * This class represents a mapper for front-end users.
 *
 * @extends AbstractDataMapper<FrontEndUser>
 */
class FrontEndUserMapper extends AbstractDataMapper
{
    protected $tableName = 'fe_users';

    protected $modelClassName = FrontEndUser::class;

    protected $relations = [
        'tx_seminars_registration' => RegistrationMapper::class,
    ];
}
