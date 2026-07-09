<?php

declare(strict_types=1);

it('keeps artisan commands available when larapilot is disabled', function (): void {
    $this->artisan('larapilot:doctor')->assertSuccessful();
    $this->artisan('larapilot:config-show')->assertSuccessful();
});

it('does not register dev routes when larapilot is disabled', function (): void {
    $mockupDir = base_path('.larapilot/mockups/US-001');
    mkdir($mockupDir, 0755, true);
    file_put_contents($mockupDir.'/index.html', '<html><body>Mockup</body></html>');

    $this->get('/mockups/US-001')->assertNotFound();
    $this->get('/larapilot')->assertNotFound();
});
