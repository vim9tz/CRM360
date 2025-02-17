<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Block;
use App\Models\Items;
use App\Models\School;
use App\Models\DraftMou;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Resources\Resource;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Grid;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Group;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;

use Illuminate\Support\Facades\Storage;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\DraftMouResource\Pages;

class DraftMouResource extends Resource
{

    protected static ?string $navigationIcon = 'heroicon-o-document';

    public static function canViewAny(): bool
    {
        return auth()->user()->hasRole(['admin', 'sales_head', 'sales_operation']);
    }




    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                /*** 🏫 School & Contract Details ***/
                Wizard::make([
                    Wizard\Step::make('School & Contract Details')
                        ->schema([
                Section::make('School & Contract Details')
                    ->description('Enter the school information and the services being provided.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('date')
                                    ->label('Agreement Date')
                                    ->default(now())
                                    ->required(),

                                    Forms\Components\Select::make('state_id')
                                    ->label('State')
                                    ->options(\App\Models\State::pluck('name', 'id')->toArray()) // Fetch states using Eloquent
                                    ->reactive()
                                    ->required()
                                    ->afterStateUpdated(fn(callable $set) => $set('district_id', null)), // Reset district when state changes

                                Forms\Components\Select::make('district_id')
                                    ->label('District')
                                    ->options(function (callable $get) {
                                        $stateId = $get('state_id');
                                        if (!$stateId) {
                                            return [];
                                        }
                                        // Fetch districts for the chosen state
                                        return \App\Models\District::where('state_id', $stateId)->pluck('name', 'id')->toArray();
                                    })
                                    ->reactive()
                                    ->required()
                                    ->afterStateUpdated(fn(callable $set) => $set('block_id', null)),

                                Forms\Components\Select::make('block_id')
                                    ->label('Block')
                                    ->options(function (callable $get) {
                                        $districtId = $get('district_id');
                                        if (!$districtId) {
                                            return [];
                                        }
                                        return Block::where('district_id', $districtId)->pluck('name', 'id')->toArray(); // Fetch blocks using Eloquent
                                    })
                                    ->reactive()
                                    ->required(),

                                Forms\Components\Select::make('school_id')
                                    ->label('School')

                                    ->options(function (callable $get) {
                                        $blockId = $get('block_id');
                                        if (!$blockId) {
                                            return [];
                                        }
                                        return School::where('block_id', $blockId)->pluck('name', 'id');
                                    })

                                    ->reactive()
                                    ->required(),

                                Textarea::make('school_address')
                                    ->label('School Address')
                                    ->rows(2)
                                    ->required(),

                                Select::make('items_id') // Change to a select input for items
                                    ->label('Item')
                                    ->options(Items::pluck('name', 'id')->toArray()) // Fetch items from the Items model
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(fn (callable $set, $state) =>
                                        $set('item_remarks', Items::where('id', $state)->value('remarks'))
                                    ),


                                Textarea::make('item_remarks') // ✅ Auto-populated field
                                    ->label('Item Remarks')
                                    ->disabled()
                                    ->dehydrated(),

                                // TextInput::make('created_by')
                                //     ->label('Created By')
                                //     ->disabled()
                                //     ->hidden()
                                //     ->dehydrated(),

                            ]),
                        ]),
                    ]),

                Wizard\Step::make('Academic Year Details')
                    ->schema([
                /*** 📅 Academic Year ***/
                Section::make('Academic Year Details')
                    ->description('Define the academic year range and course termination details.')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('agreement_period')
                                    ->label('Agreement Period (in years)')
                                    ->numeric()
                                    ->required()
                                    ->live(),

                                DatePicker::make('academic_year_start')
                                    ->label('Start Date')
                                    ->required()
                                    ->live(),

                                DatePicker::make('academic_year_end')
                                    ->label('End Date')
                                    ->required()
                                    ->reactive()
                                    ->live(),

                                DatePicker::make('course_duration_end')
                                    ->label('Course Termination Date')
                                    ->default(fn ($get) => $get('academic_year_end'))
                                    ->disabled()
                                    ->dehydrated(),
                            ]),
                        ]),
                    ]),

                Wizard\Step::make('Class-wise Student & Fee Structure')
                    ->schema([
                /*** 🏷️ Class-wise Student & Cost Details ***/
                Section::make('Class-wise Student & Fee Structure')
                ->description('Add details about each class, including student count and per-student cost.')
                ->schema([
                    Repeater::make('classes')
                        ->label('Class-wise Student Data')
                        ->schema([
                            Grid::make(4)
                                ->schema([
                                    Select::make('class')
                                        ->label('Class')
                                        ->options([
                                            'Grade 1' => 'Grade 1',
                                            'Grade 2' => 'Grade 2',
                                            'Grade 3' => 'Grade 3',
                                            'Grade 4' => 'Grade 4',
                                            'Grade 5' => 'Grade 5',
                                            'Grade 6' => 'Grade 6',
                                            'Grade 7' => 'Grade 7',
                                            'Grade 8' => 'Grade 8',
                                            'Grade 9' => 'Grade 9',
                                            'Grade 10' => 'Grade 10',
                                            'Grade 11' => 'Grade 11',
                                            'Grade 12' => 'Grade 12',
                                        ])
                                        ->required(),

                                    TextInput::make('no_of_students')
                                        ->label('Number of Students')
                                        ->numeric()
                                        ->minValue(0)
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(fn ($state, callable $set, $get) =>
                                            $set('total_cost', ($get('no_of_students') ?? 0) * ($get('cost_per_student') ?? 0))
                                        ),

                                    TextInput::make('cost_per_student')
                                        ->label('Cost Per Student')
                                        ->numeric()
                                        ->prefix('₹')
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(fn ($state, callable $set, $get) =>
                                            $set('total_cost', ($get('no_of_students') ?? 0) * ($get('cost_per_student') ?? 0))
                                        ),

                                    TextInput::make('total_cost')
                                        ->label('Total Cost')
                                        ->numeric()
                                        ->prefix('₹')
                                        ->disabled()
                                        ->dehydrated(),
                                ]),
                        ])
                        ->defaultItems(9) // Pre-loads the first 9 classes
                        ->maxItems(12) // Allows adding up to Grade 12
                        ->collapsible(),
                            ]),

                        ]),

                        Wizard\Step::make('Payment Information')
                        ->schema([              /*** 💳 Payment Details ***/
Section::make('Payment Information')
->description('Define the payment breakdown and payment mode.')
->schema([
    Grid::make(2)
        ->schema([
            Select::make('payment_type')
                ->label('Payment Type')
                ->options([
                    'amount' => 'Amount',
                    'percentage' => 'Percentage',
                ])
                ->required()
                ->live(),

            TextInput::make('payment_value')
                ->label('Total Payment Amount / Percentage')
                ->numeric()
                ->required(),
        ]),

    // Field to enter the number of installments
    TextInput::make('installments_count')
        ->label('Number of Installments')
        ->numeric()
        ->minValue(1)
        ->maxValue(12)
        ->required()
        ->live(),

    // Grid::make(3)
    //     ->schema([
            // Repeater to generate installment details dynamically
            // Repeater to generate installment details dynamically
            Repeater::make('installments')
            ->label('Installment Details')
            ->schema([
                Grid::make(4)
                    ->schema([
                        Select::make('installment')
                            ->label('Installment')
                            ->options([
                                1 => "First Payment",
                                2 => "Second Payment",
                                3 => "Third Payment",
                                4 => "Fourth Payment",
                                5 => "Fifth Payment",
                                6 => "Sixth Payment",
                                7 => "Seventh Payment",
                                8 => "Eighth Payment",
                                9 => "Ninth Payment",
                                10 => "Tenth Payment",
                                11 => "Eleventh Payment",
                                12 => "Twelfth Payment",
                            ])
                            ->required(),

                        TextInput::make('installment_payment')
                            ->label('Payment Amount/Percentage')
                            ->numeric()
                            ->required(),

                        Select::make('installment_month')
                            ->label('Month')
                            ->options([
                                'January' => 'January', 'February' => 'February', 'March' => 'March',
                                'April' => 'April', 'May' => 'May', 'June' => 'June',
                                'July' => 'July', 'August' => 'August', 'September' => 'September',
                                'October' => 'October', 'November' => 'November', 'December' => 'December',
                            ])
                            ->required(),

                        TextInput::make('installment_year')
                            ->label('Year')
                            ->numeric()
                            ->minValue(date('Y'))
                            ->maxValue(date('Y') + 5)
                            ->required(),
                    ]),
            ])
            ->default([]) // ✅ Prevents the "foreach()" error when it's NULL
            ->dehydrated() // ✅ Ensures the data is saved in JSON format
            ->live(), // ✅ Ensures real-time updates





        // ]),

    Grid::make(2)
        ->schema([
            Select::make('mode_of_payment')
                ->label('Mode of Payment')
                ->options([
                    'bank_transfer' => 'Bank Transfer',
                    'cheque' => 'Cheque',
                    'neft' => 'NEFT',
                    'rtgs' => 'RTGS',
                ])
                ->required(),

            TextInput::make('due_days')
                ->label('Due Days')
                ->default(30)
                ->numeric()
                ->required(),
        ]),
    ]),
]),

Wizard\Step::make('Legal & Dispute Resolution')
->schema([
                /*** 📌 Dispute & Legal Details ***/
                Section::make('Legal & Dispute Resolution')
                    ->description('Provide legal dispute resolution details and company location.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Textarea::make('dispute_resolution')
                                    ->label('Dispute Resolution')
                                    ->rows(2)
                                    ->required(),

                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('company_city')
                                            ->label('Company City')
                                            ->required(),

                                        TextInput::make('company_state')
                                            ->label('Company State')
                                            ->required(),
                                    ]),
                            ]),
                        ]),
                    ]),
            ])
                    ->columnSpan('full') // Ensures full width
                ->maxWidth('7xl') // Increases form width for better UI
                    ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->label('Date')
                    ->sortable()
                    ->date('Y-m-d'),

                TextColumn::make('school.name')
                    ->label('School Name')
                    ->sortable(),

                TextColumn::make('academic_year_start')
                    ->label('Academic Year')
                    ->formatStateUsing(fn ($record) => $record->academic_year_start?->format('Y-m-d') . ' - ' . $record->academic_year_end?->format('Y-m-d')),

                TextColumn::make('total_students')
                    ->label('Total Students')
                    ->formatStateUsing(fn ($record) => collect($record->classes)->sum('no_of_students')),

                TextColumn::make('total_revenue')
                    ->label('Total Revenue')
                    ->money('INR')
                    ->formatStateUsing(fn ($record) => collect($record->classes)
                        ->sum(fn ($class) => ($class['no_of_students'] ?? 0) * ($class['cost_per_student'] ?? 0))),
            ])
            ->filters([
                Filter::make('class')
                    ->form([
                        TextInput::make('class')
                            ->numeric()
                            ->placeholder('Enter class number')
                    ])
                    ->query(fn (Builder $query, array $data) =>
                        $query->when($data['class'] ?? null, fn ($q, $value) =>
                            $q->whereJsonContains('classes', [['class' => (int)$value]])
                        )
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),


                Action::make('download_pdf')
                ->label('Download PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn ($record) => route('draft-mou.download', $record->id))
                ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'asc')
            ->paginated([10, 25,]);

    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDraftMous::route('/'),
            'create' => Pages\CreateDraftMou::route('/create'),
            'edit' => Pages\EditDraftMou::route('/{record}/edit'),
        ];
    }
}
