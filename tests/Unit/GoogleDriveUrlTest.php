<?php

namespace Tests\Unit;

use App\Rules\GoogleDriveUrl;
use PHPUnit\Framework\TestCase;

class GoogleDriveUrlTest extends TestCase
{
    public function test_accepts_supported_https_google_drive_and_docs_links(): void
    {
        foreach ([
            'https://drive.google.com/file/d/file-id/view?usp=sharing',
            'https://drive.google.com/drive/folders/folder-id',
            'https://drive.google.com/open?id=file-id',
            'https://docs.google.com/document/d/document-id/edit',
            'https://docs.google.com/forms/d/e/form-id/viewform',
        ] as $url) {
            $this->assertTrue(GoogleDriveUrl::isValid($url), $url);
        }
    }

    public function test_rejects_urls_outside_the_supported_google_drive_patterns(): void
    {
        foreach ([
            'http://drive.google.com/file/d/file-id/view',
            'https://example.com/file/d/file-id/view',
            'https://drive.google.com.example.com/file/d/file-id/view',
            'https://user@drive.google.com/file/d/file-id/view',
            'https://drive.google.com:443/file/d/file-id/view',
            'https://drive.google.com/',
            'not a URL',
            'https://drive.google.com/file/d/'.str_repeat('a', 256).'/view',
        ] as $url) {
            $this->assertFalse(GoogleDriveUrl::isValid($url), $url);
        }
    }
}
