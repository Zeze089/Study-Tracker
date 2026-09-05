import Alpine from 'alpinejs';
import {
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    LinearScale,
    Tooltip,
} from 'chart.js';

window.Alpine = Alpine;
Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

window.themeToggle = function () {
    return {
        theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',

        init() {
            window.addEventListener('storage', (event) => {
                if (event.key === 'study-tracker-theme') {
                    this.applyTheme(event.newValue === 'dark' ? 'dark' : 'light', false);
                }
            });
        },

        toggle() {
            this.applyTheme(this.theme === 'dark' ? 'light' : 'dark');
        },

        applyTheme(theme, persist = true) {
            this.theme = theme;

            if (persist) {
                try {
                    localStorage.setItem('study-tracker-theme', theme);
                } catch (error) {
                    // Keep the in-memory theme even when storage is unavailable.
                }
            }

            document.documentElement.classList.toggle('dark', theme === 'dark');
            document.documentElement.style.colorScheme = theme;
            window.dispatchEvent(new CustomEvent('study-theme-changed', {
                detail: {
                    theme,
                },
            }));
        },
    };
};

window.studyRecordForm = function (config) {
    return {
        modalOpen: false,
        submitting: false,
        successMessage: '',
        errorMessage: '',
        errors: {},
        record: config.record,
        selectedDate: config.date,
        selectedDateLabel: config.dateLabel,
        availableSubjects: config.subjects,
        subjects: config.subjects,
        form: null,
        nextItemKey: 1,

        init() {
            this.form = this.formFromRecord(this.record, this.selectedDate);
        },

        openModal(detail = null) {
            this.successMessage = '';
            this.errorMessage = '';
            this.errors = {};

            if (detail?.date) {
                this.selectedDate = detail.date;
                this.selectedDateLabel = detail.dateLabel || detail.date;
                this.record = detail.record || null;
            }

            this.form = this.formFromRecord(this.record, this.selectedDate);
            this.modalOpen = true;
        },

        closeModal() {
            if (!this.submitting) {
                this.modalOpen = false;
            }
        },

        formFromRecord(record, date) {
            this.subjects = [...this.availableSubjects];
            const items = this.itemsFromRecord(record);
            this.ensureSelectedSubjects(items);

            return {
                study_date: record?.study_date || date,
                studied: record ? String(Number(record.studied)) : '1',
                items,
                notes: record?.notes ?? '',
            };
        },

        statusLabel() {
            if (!this.record) {
                return 'Nao registrado';
            }

            return this.record.studied ? 'Estudou' : 'Nao estudou';
        },

        statusClasses() {
            if (!this.record) {
                return 'bg-slate-100 text-slate-700';
            }

            return this.record.studied
                ? 'bg-emerald-50 text-emerald-700'
                : 'bg-rose-50 text-rose-700';
        },

        subjectLabel() {
            return this.record?.subjects_label || this.record?.subject_name || 'Sem materia';
        },

        durationLabel() {
            return this.record?.duration_label || 'Sem tempo informado';
        },

        contentLabel() {
            if (this.record?.content_label || this.record?.content) {
                return this.record.content_label || this.record.content;
            }

            if (this.record?.items?.length > 1) {
                return `${this.record.items.length} itens registrados`;
            }

            return 'Sem conteudo informado';
        },

        firstError(field) {
            return this.errors[field]?.[0] || '';
        },

        requestPayload() {
            return {
                study_date: this.form.study_date,
                studied: this.form.studied === '1',
                items: this.form.studied === '1' ? this.normalizedItems() : [],
                notes: this.form.notes || null,
            };
        },

        itemsFromRecord(record) {
            if (record?.items?.length > 0) {
                return record.items.map((item) => ({
                    key: this.nextItemKey++,
                    subject_id: item.subject_id ?? '',
                    subject_name: item.subject_name,
                    subject_color: item.subject_color,
                    content: item.content ?? '',
                    hours: item.hours ?? '',
                    time_minutes: item.time_minutes ?? '',
                }));
            }

            if (record && (record.subject_id || record.content || record.minutes !== null)) {
                return [{
                    key: this.nextItemKey++,
                    subject_id: record.subject_id ?? '',
                    subject_name: record.subject_name,
                    subject_color: record.subject_color,
                    content: record.content ?? '',
                    hours: record.hours ?? '',
                    time_minutes: record.time_minutes ?? '',
                }];
            }

            return [this.blankStudyItem()];
        },

        blankStudyItem() {
            return {
                key: this.nextItemKey++,
                subject_id: '',
                subject_name: null,
                subject_color: null,
                content: '',
                hours: '',
                time_minutes: '',
            };
        },

        addItem() {
            this.form.items.push(this.blankStudyItem());
        },

        removeItem(index) {
            if (this.form.items.length === 1) {
                this.form.items = [this.blankStudyItem()];
                return;
            }

            this.form.items.splice(index, 1);
        },

        normalizedItems() {
            return this.form.items
                .map((item) => ({
                    subject_id: item.subject_id === '' ? null : Number(item.subject_id),
                    content: item.content || null,
                    hours: item.hours === '' ? null : Number(item.hours),
                    time_minutes: item.time_minutes === '' ? null : Number(item.time_minutes),
                }))
                .filter((item) => !this.itemIsBlank(item));
        },

        itemIsBlank(item) {
            return item.subject_id === null
                && item.content === null
                && item.hours === null
                && item.time_minutes === null;
        },

        itemsDurationLabel() {
            const minutes = this.form.items.reduce((total, item) => {
                const hours = item.hours === '' ? 0 : Number(item.hours);
                const timeMinutes = item.time_minutes === '' ? 0 : Number(item.time_minutes);

                return total + (hours * 60) + timeMinutes;
            }, 0);

            return this.formatDuration(minutes);
        },

        formatDuration(minutes) {
            const hours = Math.floor(minutes / 60);
            const remainingMinutes = minutes % 60;

            if (hours === 0) {
                return `${remainingMinutes}min`;
            }

            if (remainingMinutes === 0) {
                return `${hours}h`;
            }

            return `${hours}h ${remainingMinutes}min`;
        },

        ensureSelectedSubjects(items) {
            items.forEach((item) => {
                if (!item.subject_id || this.subjects.some((subject) => Number(subject.id) === Number(item.subject_id))) {
                    return;
                }

                this.subjects = [
                    ...this.subjects,
                    {
                        id: item.subject_id,
                        name: item.subject_name || 'Materia inativa',
                        color: item.subject_color || '#64748b',
                    },
                ];
            });
        },

        async save() {
            this.submitting = true;
            this.successMessage = '';
            this.errorMessage = '';
            this.errors = {};

            try {
                const response = await fetch(config.storeUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(this.requestPayload()),
                });

                const data = await response.json();

                if (!response.ok) {
                    this.errors = data.errors || {};
                    this.errorMessage = data.message || 'Nao foi possivel salvar o registro.';
                    return;
                }

                this.record = data.record;
                this.selectedDate = data.record.study_date;
                this.selectedDateLabel = this.formatDateLabel(data.record.study_date);
                this.form = this.formFromRecord(this.record, this.selectedDate);
                this.successMessage = data.message || 'Registro salvo com sucesso.';
                this.modalOpen = false;
                window.dispatchEvent(new CustomEvent('study-record-saved', {
                    detail: {
                        record: this.record,
                    },
                }));
            } catch (error) {
                this.errorMessage = 'Nao foi possivel conectar ao servidor.';
            } finally {
                this.submitting = false;
            }
        },

        formatDateLabel(date) {
            const [year, month, day] = date.split('-');

            return `${day}/${month}/${year}`;
        },
    };
};

window.studyCalendar = function (config) {
    return {
        days: config.days,
        summary: config.summary,
        month: config.month,
        year: config.year,
        today: config.today,

        day(date) {
            return this.days[date];
        },

        updateDay(event) {
            const record = event.detail?.record;

            if (!record || !this.days[record.study_date]) {
                return;
            }

            const day = this.days[record.study_date];
            const previousRecord = day.record;

            day.record = record;
            day.status = record.studied ? 'studied' : 'not_studied';
            day.status_label = record.studied ? 'Estudou' : 'Nao estudou';
            day.subject_name = record.subjects_label || record.subject_name;
            day.subject_color = record.subject_color;
            day.content = record.content_label || record.content;
            day.duration_label = record.duration_label;
            day.aria_label = `${this.formatDateLabel(record.study_date)}: ${day.status_label}${day.is_today ? ', hoje' : ''}`;

            this.updateSummary(previousRecord, record);
        },

        updateSummary(previousRecord, record) {
            if (record.study_date > this.today) {
                return;
            }

            if (previousRecord) {
                if (previousRecord.studied) {
                    this.summary.studied_days -= 1;
                    this.summary.total_minutes -= previousRecord.minutes || 0;
                } else {
                    this.summary.not_studied_days -= 1;
                }
            } else {
                this.summary.unregistered_days = Math.max(0, this.summary.unregistered_days - 1);
            }

            if (record.studied) {
                this.summary.studied_days += 1;
                this.summary.total_minutes += record.minutes || 0;
            } else {
                this.summary.not_studied_days += 1;
            }

            this.summary.total_hours = Math.round((this.summary.total_minutes / 60) * 10) / 10;
            this.summary.consistency = this.summary.elapsed_days > 0
                ? Math.round((this.summary.studied_days / this.summary.elapsed_days) * 100)
                : 0;
        },

        formatDateLabel(date) {
            const [year, month, day] = date.split('-');

            return `${day}/${month}/${year}`;
        },

        openLabel(date) {
            const day = this.day(date);

            return day.record ? `Editar registro de ${day.date_label}` : `Registrar estudo de ${day.date_label}`;
        },
    };
};

window.subjectManager = function (config) {
    return {
        modalOpen: config.initialModalOpen || false,
        subjects: config.subjects,
        storeUrl: config.storeUrl,
        form: config.initialForm,

        openCreate() {
            this.form = {
                id: null,
                name: '',
                color: '#14b8a6',
                active: true,
                update_url: null,
                study_records_count: 0,
            };
            this.modalOpen = true;
        },

        openEdit(subject) {
            this.form = {
                id: subject.id,
                name: subject.name,
                color: subject.color,
                active: subject.active,
                update_url: subject.update_url,
                study_records_count: subject.study_records_count,
            };
            this.modalOpen = true;
        },

        closeModal() {
            this.modalOpen = false;
        },

        isEditing() {
            return Boolean(this.form?.id);
        },

        formAction() {
            return this.isEditing() ? this.form.update_url : this.storeUrl;
        },

        confirmSubmit(event) {
            if (!this.isEditing() || this.form.active) {
                return;
            }

            if (!window.confirm('Desativar esta materia remove a opcao de novos registros, mas preserva o historico.')) {
                event.preventDefault();
            }
        },
    };
};

window.studyDashboard = function (config) {
    return {
        stats: config.stats,
        statsUrl: config.statsUrl,
        chart: null,
        loading: false,
        errorMessage: '',

        init() {
            this.renderChart();
            window.addEventListener('study-theme-changed', () => this.renderChart());
        },

        async refresh() {
            this.loading = true;
            this.errorMessage = '';

            try {
                const response = await fetch(this.statsUrl, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (!response.ok) {
                    this.errorMessage = 'Nao foi possivel atualizar o Dashboard.';
                    return;
                }

                this.stats = await response.json();
                this.renderChart();
            } catch (error) {
                this.errorMessage = 'Nao foi possivel atualizar o Dashboard.';
            } finally {
                this.loading = false;
            }
        },

        renderChart() {
            this.$nextTick(() => {
                const canvas = this.$refs.recentChart;

                if (!canvas) {
                    return;
                }

                const palette = this.chartPalette();
                const labels = this.stats.recent_chart.map((day) => day.label);
                const values = this.stats.recent_chart.map((day) => day.minutes);

                if (this.chart) {
                    this.chart.data.labels = labels;
                    this.chart.data.datasets[0].data = values;
                    this.chart.data.datasets[0].backgroundColor = palette.bar;
                    this.chart.options.plugins.tooltip.backgroundColor = palette.tooltipBackground;
                    this.chart.options.plugins.tooltip.titleColor = palette.tooltipText;
                    this.chart.options.plugins.tooltip.bodyColor = palette.tooltipText;
                    this.chart.options.plugins.tooltip.borderColor = palette.tooltipBorder;
                    this.chart.options.scales.x.ticks.color = palette.mutedText;
                    this.chart.options.scales.y.ticks.color = palette.mutedText;
                    this.chart.options.scales.y.grid.color = palette.grid;
                    this.chart.update();

                    return;
                }

                this.chart = new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            data: values,
                            backgroundColor: palette.bar,
                            borderRadius: 6,
                            maxBarThickness: 44,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false,
                            },
                            tooltip: {
                                backgroundColor: palette.tooltipBackground,
                                titleColor: palette.tooltipText,
                                bodyColor: palette.tooltipText,
                                borderColor: palette.tooltipBorder,
                                borderWidth: 1,
                                callbacks: {
                                    label: (context) => `${context.parsed.y} min`,
                                },
                            },
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false,
                                },
                                ticks: {
                                    color: palette.mutedText,
                                    font: {
                                        size: 12,
                                        weight: 600,
                                    },
                                },
                            },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0,
                                    color: palette.mutedText,
                                },
                                grid: {
                                    color: palette.grid,
                                },
                            },
                        },
                    },
                });
            });
        },

        chartPalette() {
            const dark = document.documentElement.classList.contains('dark');

            return {
                bar: dark ? '#2dd4bf' : '#0f766e',
                grid: dark ? 'rgba(51, 65, 85, 0.78)' : '#e2e8f0',
                mutedText: dark ? '#94a3b8' : '#64748b',
                tooltipBackground: dark ? '#020617' : '#ffffff',
                tooltipBorder: dark ? '#334155' : '#cbd5e1',
                tooltipText: dark ? '#e2e8f0' : '#0f172a',
            };
        },

        todayStatusLabel() {
            if (!this.stats.today_record) {
                return 'Nenhum estudo registrado ainda.';
            }

            return this.stats.today_record.studied ? 'Estudou' : 'Nao estudou';
        },

        todayStatusClasses() {
            if (!this.stats.today_record) {
                return 'bg-slate-100 text-slate-700';
            }

            return this.stats.today_record.studied
                ? 'bg-emerald-50 text-emerald-700'
                : 'bg-rose-50 text-rose-700';
        },

        buttonLabel() {
            return this.stats.today_record ? 'Editar registro' : 'Registrar estudo';
        },
    };
};

Alpine.start();
