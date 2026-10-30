<?php

namespace Tests\Feature\Multilingual;

use App\Models\Conference;
use App\Models\ReviewFormItem;
use App\Models\ScheduledConference;
use App\Models\SubmissionFileType;
use App\Models\User;
use App\Panel\ScheduledConference\Livewire\ReviewFormTable;
use App\Panel\ScheduledConference\Livewire\SubmissionFileTypeTable;
use App\Providers\PanelProvider;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Feature\Multilingual\Concerns\InteractsWithContentLanguages;
use Tests\TestCase;

class FileTypeAndReviewFormTranslationTest extends TestCase
{
    use InteractsWithContentLanguages;
    use RefreshDatabase;

    protected Conference $conference;

    protected ScheduledConference $scheduledConference;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conference = $this->makeConference();
        $this->scheduledConference = $this->makeScheduledConference($this->conference);
        $this->useConference($this->conference, $this->scheduledConference);

        Filament::setCurrentPanel(Filament::getPanel(PanelProvider::PANEL_SCHEDULED_CONFERENCE));

        Gate::before(fn () => true);

        $this->actingAs(User::query()->create([
            'given_name' => 'Content',
            'family_name' => 'Manager',
            'email' => 'content-manager@example.test',
            'password' => 'password123456',
        ]));

        $this->enableLanguages(['en', 'id']);
    }

    public function test_the_file_type_form_saves_every_language(): void
    {
        Livewire::test(SubmissionFileTypeTable::class)
            ->loadTable()
            ->mountTableAction('create')
            ->assertSeeHtml('mountedTableActionsData.0.name.en')
            ->assertSeeHtml('mountedTableActionsData.0.name.id')
            ->setTableActionData(['name' => ['en' => '', 'id' => 'Naskah']])
            ->callMountedTableAction()
            ->assertHasTableActionErrors(['name.en' => 'required'])
            ->setTableActionData(['name' => ['en' => 'Manuscript', 'id' => 'Naskah'], 'required' => true])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $type = SubmissionFileType::query()->where('name', 'Manuscript')->firstOrFail();

        $this->assertTrue($type->required);
        $this->assertSame('Naskah', $type->translated('name', 'id'));

        Livewire::test(SubmissionFileTypeTable::class)
            ->loadTable()
            ->mountTableAction('edit', $type)
            ->assertTableActionDataSet(['name' => ['en' => 'Manuscript', 'id' => 'Naskah']])
            ->setTableActionData(['name' => ['en' => 'Full manuscript', 'id' => 'Naskah lengkap']])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $type = SubmissionFileType::query()->find($type->getKey());

        $this->assertSame('Full manuscript', $type->name);
        $this->assertSame('Naskah lengkap', $type->translated('name', 'id'));

        app()->setLocale('id');

        Livewire::test(SubmissionFileTypeTable::class)
            ->loadTable()
            ->assertSee('Naskah lengkap')
            ->assertDontSee('Full manuscript');
    }

    public function test_the_review_form_item_form_saves_every_language(): void
    {
        Livewire::test(ReviewFormTable::class)
            ->loadTable()
            ->mountTableAction('create')
            ->assertSeeHtml('mountedTableActionsData.0.label.id')
            ->assertSeeHtml('mountedTableActionsData.0.meta.description.id')
            ->setTableActionData([
                'type' => ReviewFormItem::TYPE_RADIO,
                'label' => ['en' => 'Originality', 'id' => 'Orisinalitas'],
                'meta.description' => ['en' => 'Is the work new?', 'id' => 'Apakah karya ini baru?'],
            ])
            ->assertSeeHtml('mountedTableActionsData.0.meta.radio_options.id')
            ->set('mountedTableActionsData.0.meta.radio_options', [
                'en' => ['a' => ['option' => 'Yes'], 'b' => ['option' => 'No']],
                'id' => ['a' => ['option' => 'Ya']],
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $item = ReviewFormItem::query()->sole();

        $this->assertSame('Originality', $item->label);
        $this->assertSame('Is the work new?', $item->getMeta('description'));
        $this->assertSame(['Yes', 'No'], $item->getMeta('radio_options'));
        $this->assertSame('Orisinalitas', $item->translated('label', 'id'));
        $this->assertSame('Apakah karya ini baru?', $item->translated('description', 'id'));
        $this->assertSame([0 => 'Ya', 1 => 'No'], $item->translatedOptions('radio_options', 'id'));

        Livewire::test(ReviewFormTable::class)
            ->loadTable()
            ->mountTableAction('edit', $item)
            ->assertTableActionDataSet([
                'label' => ['en' => 'Originality', 'id' => 'Orisinalitas'],
                'meta.description' => ['en' => 'Is the work new?', 'id' => 'Apakah karya ini baru?'],
            ])
            ->setTableActionData(['label' => ['en' => 'Novelty', 'id' => 'Kebaruan']])
            ->set('mountedTableActionsData.0.meta.radio_options', [
                'en' => ['a' => ['option' => 'Yes'], 'b' => ['option' => 'No']],
                'id' => ['a' => ['option' => 'Ya'], 'b' => ['option' => 'Tidak']],
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $item = ReviewFormItem::query()->sole();

        $this->assertSame('Novelty', $item->label);
        $this->assertSame('Kebaruan', $item->translated('label', 'id'));
        $this->assertSame(['Ya', 'Tidak'], $item->translatedOptions('radio_options', 'id'));

        app()->setLocale('id');

        Livewire::test(ReviewFormTable::class)
            ->loadTable()
            ->assertSee('Kebaruan')
            ->assertDontSee('Novelty');
    }

    public function test_select_option_labels_are_entered_per_language_and_keep_their_values(): void
    {
        $component = Livewire::test(ReviewFormTable::class)
            ->loadTable()
            ->mountTableAction('create')
            ->setTableActionData([
                'type' => ReviewFormItem::TYPE_SELECT,
                'label' => ['en' => 'Clarity', 'id' => 'Kejelasan'],
                'weight' => 20,
            ])
            ->set('mountedTableActionsData.0.meta.select_options', [
                'a' => ['value' => 1, 'label' => ['en' => 'Poor', 'id' => 'Buruk']],
                'b' => ['value' => 5, 'label' => ['en' => 'Good', 'id' => '']],
            ])
            ->assertSeeHtml('mountedTableActionsData.0.meta.select_options.a.label.id')
            ->assertSeeHtml('mountedTableActionsData.0.meta.select_options.b.label.id');

        $component
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $item = ReviewFormItem::query()->sole();

        $this->assertSame([['value' => 1, 'label' => 'Poor'], ['value' => 5, 'label' => 'Good']], $item->getMeta('select_options'));
        $this->assertFalse($item->hasMeta('select_option_labels'));
        $this->assertSame([0 => 'Buruk', 1 => 'Good'], $item->translatedOptions('select_option_labels', 'id'));

        Livewire::test(ReviewFormTable::class)
            ->loadTable()
            ->mountTableAction('edit', $item)
            ->assertTableActionDataSet([
                'meta.select_options' => fn (array $options) => array_values($options) === [
                    ['value' => 1, 'label' => ['en' => 'Poor', 'id' => 'Buruk']],
                    ['value' => 5, 'label' => ['en' => 'Good', 'id' => null]],
                ],
            ])
            ->set('mountedTableActionsData.0.meta.select_options', [
                'a' => ['value' => 1, 'label' => ['en' => 'Weak', 'id' => 'Lemah']],
                'b' => ['value' => 5, 'label' => ['en' => 'Good', 'id' => 'Baik']],
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $item = ReviewFormItem::query()->sole();

        $this->assertSame([['value' => 1, 'label' => 'Weak'], ['value' => 5, 'label' => 'Good']], $item->getMeta('select_options'));
        $this->assertSame([0 => 'Lemah', 1 => 'Baik'], $item->translatedOptions('select_option_labels', 'id'));

        app()->setLocale('id');

        $this->assertSame([1 => 'Lemah', 5 => 'Baik'], $item->getFormField()->getOptions());
        $this->assertSame('Baik', $item->getContentFromValue(5));

        app()->setLocale('en');

        $this->assertSame([1 => 'Weak', 5 => 'Good'], $item->getFormField()->getOptions());
    }

    public function test_reviewers_see_the_review_form_in_their_language(): void
    {
        $checkbox = $this->makeReviewFormItem(ReviewFormItem::TYPE_CHECKBOX, 'Strengths', [
            'description' => 'Pick every strength',
            'checkbox_options' => ['Method', 'Data'],
        ]);
        $checkbox->setTranslation('label', 'id', 'Kelebihan');
        $checkbox->setTranslation('description', 'id', 'Pilih semua kelebihan');
        $checkbox->setTranslation('checkbox_options', 'id', ['Metode', 'Data']);

        $radio = $this->makeReviewFormItem(ReviewFormItem::TYPE_RADIO, 'Accept?', ['radio_options' => ['Yes', 'No']]);
        $radio->setTranslation('radio_options', 'id', ['Ya']);

        app()->setLocale('id');

        $field = ReviewFormItem::query()->find($checkbox->getKey())->getFormField();

        $this->assertInstanceOf(CheckboxList::class, $field);
        $this->assertSame('Kelebihan', $field->getLabel());
        $this->assertSame('Pilih semua kelebihan', (string) $field->getHelperText());
        $this->assertSame([0 => 'Metode', 1 => 'Data'], $field->getOptions());
        $this->assertSame('Metode, Data', $checkbox->getContentFromValue([0, 1]));

        $field = ReviewFormItem::query()->find($radio->getKey())->getFormField();

        $this->assertInstanceOf(Radio::class, $field);
        $this->assertSame('Accept?', $field->getLabel());
        $this->assertSame([0 => 'Ya', 1 => 'No'], $field->getOptions());
        $this->assertSame('No', $radio->getContentFromValue(1));

        app()->setLocale('en');

        $this->assertSame('Strengths', ReviewFormItem::query()->find($checkbox->getKey())->getFormField()->getLabel());
        $this->assertSame('Method, Data', $checkbox->getContentFromValue([0, 1]));
    }

    public function test_copying_a_review_form_item_copies_its_translations(): void
    {
        $item = $this->makeReviewFormItem(ReviewFormItem::TYPE_SELECT, 'Clarity', [
            'description' => 'How clear is it?',
            'select_options' => [['value' => 1, 'label' => 'Poor'], ['value' => 5, 'label' => 'Good']],
        ]);
        $item->setTranslation('label', 'id', 'Kejelasan');
        $item->setTranslation('description', 'id', 'Seberapa jelas?');
        $item->setTranslation('select_option_labels', 'id', ['Buruk', 'Baik']);

        Livewire::test(ReviewFormTable::class)
            ->loadTable()
            ->mountTableAction('copy', $item)
            ->assertTableActionDataSet([
                'label' => ['en' => 'Clarity', 'id' => 'Kejelasan'],
                'meta.description' => ['en' => 'How clear is it?', 'id' => 'Seberapa jelas?'],
            ])
            ->setTableActionData(['label' => ['en' => 'Clarity (copy)', 'id' => 'Kejelasan (salinan)']])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $copy = ReviewFormItem::query()->where('label', 'Clarity (copy)')->firstOrFail();

        $this->assertSame('Kejelasan (salinan)', $copy->translated('label', 'id'));
        $this->assertSame('Seberapa jelas?', $copy->translated('description', 'id'));
        $this->assertSame([['value' => 1, 'label' => 'Poor'], ['value' => 5, 'label' => 'Good']], $copy->getMeta('select_options'));
        $this->assertSame([0 => 'Buruk', 1 => 'Baik'], $copy->translatedOptions('select_option_labels', 'id'));
    }

    public function test_a_single_language_edit_keeps_the_primary_option_labels_in_step(): void
    {
        $item = $this->makeReviewFormItem(ReviewFormItem::TYPE_SELECT, 'Clarity', [
            'select_options' => [['value' => 1, 'label' => 'Poor'], ['value' => 5, 'label' => 'Good']],
        ]);
        $item->setTranslation('select_option_labels', 'en', ['Poor', 'Good']);
        $item->setTranslation('select_option_labels', 'id', ['Buruk', 'Baik']);

        $this->enableLanguages(['en']);

        Livewire::test(ReviewFormTable::class)
            ->loadTable()
            ->mountTableAction('edit', $item)
            ->set('mountedTableActionsData.0.meta.select_options', [
                'a' => ['value' => 1, 'label' => 'Weak'],
                'b' => ['value' => 5, 'label' => 'Good'],
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $item = ReviewFormItem::query()->sole();

        $this->assertSame([1 => 'Weak', 5 => 'Good'], $item->getSelectOptions());
        $this->assertSame([0 => 'Buruk', 1 => 'Baik'], $item->translatedOptions('select_option_labels', 'id'));
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    protected function makeReviewFormItem(int $type, string $label, array $meta = []): ReviewFormItem
    {
        $item = ReviewFormItem::query()->create(['type' => $type, 'label' => $label]);
        $item->setManyMeta($meta);

        return $item;
    }
}
