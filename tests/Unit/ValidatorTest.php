<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Lang;
use App\Core\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    /** @var list<string> */
    private array $tmpFiles = [];

    protected function setUp(): void
    {
        Lang::init(dirname(__DIR__, 2) . '/lang', ['fr', 'ar', 'en'], 'fr');
        Validator::setPresenceVerifier(static fn (string $table, string $column, mixed $value, mixed $ignore): bool => $table === 'users' && $column === 'email' && $value === 'deja@pris.ml' && (string) $ignore !== '7');
    }

    protected function tearDown(): void
    {
        Validator::setPresenceVerifier(null);
        foreach ($this->tmpFiles as $file) {
            @unlink($file);
        }
    }

    public function testValidDataPasses(): void
    {
        $v = Validator::make(
            ['full_name' => 'Aminata Traoré', 'email' => 'aminata@example.ml', 'phone' => '+22376123456', 'age' => '30'],
            ['full_name' => 'required|string|max:120', 'email' => 'required|email', 'phone' => 'nullable|phone', 'age' => 'integer|between:18,120'],
        );

        self::assertTrue($v->passes());
        self::assertSame([], $v->errors());
    }

    public function testRequiredFailsOnEmptyValuesWithTranslatedMessage(): void
    {
        $v = Validator::make(['full_name' => '   '], ['full_name' => 'required']);

        self::assertTrue($v->fails());
        self::assertSame('Le champ nom complet est obligatoire.', $v->first('full_name'));
    }

    public function testMessagesFollowTheCurrentLocale(): void
    {
        Lang::setLocale('ar');
        self::assertSame('حقل البريد الإلكتروني مطلوب.', Validator::make([], ['email' => 'required'])->first('email'));

        Lang::setLocale('en');
        self::assertSame('The email address field is required.', Validator::make([], ['email' => 'required'])->first('email'));
    }

    public function testOptionalEmptyFieldsSkipOtherRules(): void
    {
        $v = Validator::make(['email' => '', 'phone' => null], ['email' => 'nullable|email', 'phone' => 'phone']);

        self::assertTrue($v->passes());
    }

    /** @return iterable<string, array{string, bool}> */
    public static function phones(): iterable
    {
        yield 'Mali' => ['+22376123456', true];
        yield 'Burkina Faso' => ['+22670123456', true];
        yield 'Côte d\'Ivoire' => ['+2250701234567', true];
        yield 'France (pays non ouvert)' => ['+33612345678', false];
        yield 'sans indicatif' => ['76123456', false];
        yield 'avec espaces' => ['+223 76 12 34 56', false];
        yield 'trop court' => ['+2231234', false];
        yield 'lettres' => ['+223ABCDEFGH', false];
    }

    #[DataProvider('phones')]
    public function testPhoneRuleRequiresE164(string $phone, bool $valid): void
    {
        self::assertSame($valid, Validator::make(['phone' => $phone], ['phone' => 'phone'])->passes());
    }

    public function testEmailRule(): void
    {
        self::assertTrue(Validator::make(['e' => 'contact@darulfatwa.com'], ['e' => 'email'])->passes());
        self::assertTrue(Validator::make(['e' => 'pas-un-email'], ['e' => 'email'])->fails());
    }

    public function testMinAndMaxDependOnType(): void
    {
        // Texte : nombre de caractères (multi-octets compris)
        self::assertTrue(Validator::make(['t' => 'سؤال'], ['t' => 'min:4'])->passes());
        self::assertSame('Le champ t doit contenir au moins 5 caractères.', Validator::make(['t' => 'سؤال'], ['t' => 'min:5'])->first('t'));
        // Nombre : valeur
        self::assertSame('Le champ n ne doit pas dépasser 10.', Validator::make(['n' => '11'], ['n' => 'numeric|max:10'])->first('n'));
        self::assertTrue(Validator::make(['n' => '9'], ['n' => 'numeric|max:10'])->passes());
        // Tableau : nombre d'éléments
        self::assertTrue(Validator::make(['a' => [1, 2, 3]], ['a' => 'max:2'])->fails());
    }

    public function testInRule(): void
    {
        self::assertTrue(Validator::make(['c' => 'whatsapp'], ['c' => 'in:platform,whatsapp,both'])->passes());
        self::assertTrue(Validator::make(['c' => 'sms'], ['c' => 'in:platform,whatsapp,both'])->fails());
    }

    public function testConfirmedRule(): void
    {
        self::assertTrue(Validator::make(['password' => 'Secret123', 'password_confirmation' => 'Secret123'], ['password' => 'confirmed'])->passes());
        self::assertSame(
            'La confirmation du champ mot de passe ne correspond pas.',
            Validator::make(['password' => 'Secret123', 'password_confirmation' => 'autre'], ['password' => 'confirmed'])->first('password'),
        );
    }

    public function testRequiredWithoutForEmailOrPhone(): void
    {
        $rules = ['email' => 'required_without:phone|nullable|email', 'phone' => 'required_without:email|nullable|phone'];

        self::assertTrue(Validator::make(['phone' => '+22376123456'], $rules)->passes());
        self::assertTrue(Validator::make(['email' => 'a@b.ml'], $rules)->passes());
        self::assertTrue(Validator::make([], $rules)->fails());
    }

    public function testUniqueAndExistsUseThePresenceVerifier(): void
    {
        self::assertTrue(Validator::make(['email' => 'deja@pris.ml'], ['email' => 'unique:users,email'])->fails());
        self::assertTrue(Validator::make(['email' => 'libre@ok.ml'], ['email' => 'unique:users,email'])->passes());
        // Ignorer l'enregistrement en cours de modification
        self::assertTrue(Validator::make(['email' => 'deja@pris.ml'], ['email' => 'unique:users,email,7'])->passes());
        self::assertTrue(Validator::make(['email' => 'deja@pris.ml'], ['email' => 'exists:users,email'])->passes());
    }

    public function testRegexRuleMayContainPipesWithArraySyntax(): void
    {
        $rules = ['code' => ['required', 'regex:/^(fr|ar|en)$/']];

        self::assertTrue(Validator::make(['code' => 'ar'], $rules)->passes());
        self::assertTrue(Validator::make(['code' => 'bm'], $rules)->fails());
    }

    public function testOnlyFirstFailingRuleIsReported(): void
    {
        $v = Validator::make(['email' => 'x'], ['email' => 'required|email|min:5']);

        self::assertCount(1, $v->errors()['email']);
    }

    public function testCustomMessagesAndAttributes(): void
    {
        $v = Validator::make([], ['title' => 'required'], ['title.required' => 'Merci de donner un titre (:attribute).'], ['title' => 'intitulé']);

        self::assertSame('Merci de donner un titre (intitulé).', $v->first('title'));
    }

    public function testValidatedReturnsOnlyRuledFields(): void
    {
        $v = Validator::make(['full_name' => 'Moussa', 'role_id' => 5], ['full_name' => 'required']);

        self::assertSame(['full_name' => 'Moussa'], $v->validated());
    }

    public function testFileMimesUseRealContentType(): void
    {
        // Vrai fichier PNG (1×1) nommé .png : accepté
        $png = $this->tmpFile(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=', true));
        self::assertTrue(Validator::make(['f' => $this->upload($png, 'image.png')], ['f' => 'file|mimes:png,jpg'])->passes());

        // Script PHP déguisé en .mp3 : refusé (type réel text/x-php)
        $php = $this->tmpFile('<?php echo "piège"; ?>');
        self::assertTrue(Validator::make(['audio' => $this->upload($php, 'question.mp3')], ['audio' => 'file|mimes:mp3,webm'])->fails());

        // Extension hors liste blanche : refusée même si le contenu est une image
        self::assertTrue(Validator::make(['f' => $this->upload($png, 'image.php')], ['f' => 'mimes:png'])->fails());
    }

    public function testMaxSizeInKilobytes(): void
    {
        $file = $this->tmpFile(str_repeat('a', 3000));

        self::assertTrue(Validator::make(['f' => $this->upload($file, 'a.txt')], ['f' => 'max_size:2'])->fails());
        self::assertTrue(Validator::make(['f' => $this->upload($file, 'a.txt')], ['f' => 'max_size:3'])->passes());
    }

    public function testUploadErrorIsNotAValidFile(): void
    {
        $v = Validator::make(['f' => ['name' => 'a.mp3', 'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_INI_SIZE]], ['f' => 'required|file']);

        self::assertTrue($v->fails());
    }

    public function testUnknownRuleThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Validator::make(['a' => 'x'], ['a' => 'regle_inconnue'])->passes();
    }

    private function tmpFile(string $content): string
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'dfv');
        file_put_contents($file, $content);
        $this->tmpFiles[] = $file;

        return $file;
    }

    /** @return array<string, mixed> */
    private function upload(string $path, string $name): array
    {
        return ['name' => $name, 'tmp_name' => $path, 'size' => filesize($path), 'error' => UPLOAD_ERR_OK, 'type' => 'application/octet-stream'];
    }
}
