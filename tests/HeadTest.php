<?php

declare(strict_types=1);

use API\Frontend\Head;
use PHPUnit\Framework\TestCase;

final class HeadTest extends TestCase {
    protected function setUp(): void {
        Head::reset();
    }

    public function testHigherPriorityWinsRegardlessOfOrder(): void {
        // modul nastavi behem obsahu, kostra az pri vykresleni hlavicky (po obsahu)
        Head::title("Tricko s potiskem", Head::PRIORITY_MODULE);
        Head::title("Eshop", Head::PRIORITY_DEFAULT);

        $this->assertSame("<title>Tricko s potiskem</title>", Head::render());
    }

    public function testSamePriorityLastWins(): void {
        Head::meta("description", "prvni");
        Head::meta("Description", "druhy");

        $this->assertSame('<meta name="description" content="druhy">', Head::render());
    }

    public function testValuesAreEscaped(): void {
        Head::title('A & B <script>"x"</script>');
        Head::canonical('https://web.cz/?a=1&b="2"');

        $html = Head::render();
        $this->assertStringContainsString("<title>A &amp; B &lt;script&gt;&quot;x&quot;&lt;/script&gt;</title>", $html);
        $this->assertStringContainsString('<link rel="canonical" href="https://web.cz/?a=1&amp;b=&quot;2&quot;">', $html);
    }

    public function testJsonLdCannotCloseScriptTag(): void {
        Head::jsonLd([ "@type" => "Product", "name" => "</script><b>x</b>", "price" => 199 ], "product");

        $html = Head::render();
        $this->assertStringNotContainsString("</script><b>", $html);
        // "</script>" se v JSON zapise jako </script>
        $this->assertStringContainsString(substr(json_encode("</script>", JSON_HEX_TAG | JSON_UNESCAPED_SLASHES), 1, -1), $html);
        $this->assertStringStartsWith('<script type="application/ld+json">{"@type":"Product","name":', $html);
        $this->assertStringEndsWith(',"price":199}</script>', $html);
    }

    public function testJsonLdWithoutKeyIsAppended(): void {
        Head::jsonLd([ "a" => 1 ]);
        Head::jsonLd([ "b" => 2 ]);
        $this->assertSame(2, substr_count(Head::render(), "application/ld+json"));
    }

    public function testHreflangOnePerLanguageAndGroupOrder(): void {
        Head::alternate("en", "https://web.cz/en/a/");
        Head::alternate("cs", "https://web.cz/a/");
        Head::alternate("EN", "https://web.cz/en/b/");
        Head::property("og:title", "Titulek");
        Head::title("Titulek");

        $this->assertSame(implode("\n", [
            "<title>Titulek</title>",
            '<meta property="og:title" content="Titulek">',
            '<link rel="alternate" href="https://web.cz/en/b/" hreflang="en">',
            '<link rel="alternate" href="https://web.cz/a/" hreflang="cs">',
        ]), Head::render());
    }

    public function testLinkAttributesAndRemove(): void {
        Head::link("preload", "/f.woff2", [ "as" => "font", "crossorigin" => true, "media" => null ]);
        Head::meta("robots", "noindex", Head::PRIORITY_MODULE);
        Head::remove("meta:robots");            // nizsi priorita - zustane
        $this->assertTrue(Head::has("meta:robots"));
        Head::remove("meta:robots", Head::PRIORITY_MODULE);
        $this->assertFalse(Head::has("meta:robots"));

        $this->assertSame('<link rel="preload" href="/f.woff2" as="font" crossorigin>', Head::render());
    }

    public function testRawIsNotEscaped(): void {
        Head::raw('<meta name="x" content="y">', "x");
        Head::raw('<meta name="x" content="z">', "x");
        $this->assertSame('<meta name="x" content="z">', Head::render());
    }
}
