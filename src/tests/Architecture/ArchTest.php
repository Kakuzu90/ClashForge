<?php

// Rules from specs/19 §6 that Deptrac does not cover.

arch('no debugging helpers in app code')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die'])
    ->not->toBeUsed();

arch('env() is only called from config files')
    ->expect('env')
    ->toOnlyBeUsedIn('config');
