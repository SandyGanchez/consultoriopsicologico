<?php



use App\Helpers\Helper;



$success = $_SESSION['success'] ?? null;

$error = $_SESSION['error'] ?? null;



unset(

    $_SESSION['success'],

    $_SESSION['error']

);



?>



<section

    class="appointment-page appointment-page--register"

    id="appointmentApp"

    data-url-servicios="<?= htmlspecialchars(

        Helper::baseUrl('paciente/servicios-por-psicologo'),

        ENT_QUOTES,

        'UTF-8'

    ); ?>"

    data-url-espacios="<?= htmlspecialchars(

        Helper::baseUrl('paciente/horarios-disponibles'),

        ENT_QUOTES,

        'UTF-8'

    ); ?>"

    data-url-dias="<?= htmlspecialchars(

        Helper::baseUrl('paciente/dias-disponibles'),

        ENT_QUOTES,

        'UTF-8'

    ); ?>"

    data-url-fotos="<?= htmlspecialchars(
        Helper::baseUrl('uploads/perfiles/'),
        ENT_QUOTES,
        'UTF-8'
    ); ?>"

    data-psicologo-preseleccionado="<?= htmlspecialchars(
        (string) ($psicologoPreseleccionado ?? ''),
        ENT_QUOTES,
        'UTF-8'
    ); ?>"

    data-servicio-preseleccionado="<?= htmlspecialchars(
        (string) ($servicioPreseleccionado ?? ''),
        ENT_QUOTES,
        'UTF-8'
    ); ?>"

>



    <header class="appointment-page__header">



        <h1 class="appointment-page__title">

            <i class="bi bi-calendar-plus me-2" aria-hidden="true"></i>

            Agendar una cita

        </h1>



        <p class="appointment-page__subtitle">

            Selecciona especialista, servicio, fecha y horario para

            programar tu próxima sesión.

        </p>



    </header>



    <?php if ($success): ?>



        <div class="alert alert-success mb-4" role="alert">

            <i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>

            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>

        </div>



    <?php endif; ?>



    <?php if ($error): ?>



        <div class="alert alert-danger mb-4" role="alert">

            <i class="bi bi-exclamation-triangle-fill me-2" aria-hidden="true"></i>

            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>

        </div>



    <?php endif; ?>



    <form

        action="<?= Helper::baseUrl('paciente/guardar-cita'); ?>"

        method="POST"

        id="formAgendarCita"

    >
        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
                \App\Core\Session::csrfToken(),
                ENT_QUOTES,
                'UTF-8'
            ); ?>"
        >



        <div class="appointment-layout">



            <div class="appointment-main">



                <h2 class="appointment-main__title">

                    Datos de tu cita

                </h2>



                <p class="appointment-main__intro">

                    Revisa el servicio seleccionado antes de confirmar.

                </p>



                <div

                    class="selected-service-card"

                    id="serviceCard"

                >



                    <div class="selected-service-card__body">



                        <span class="selected-service-card__label">

                            Servicio seleccionado

                        </span>



                        <p

                            class="selected-service-card__placeholder"

                            id="serviceCardPlaceholder"

                        >

                            Aún no has seleccionado un servicio

                        </p>



                        <p

                            class="selected-service-card__name d-none"

                            id="serviceCardName"

                        ></p>



                        <p

                            class="selected-service-card__meta d-none"

                            id="serviceCardMeta"

                        ></p>



                    </div>



                    <button

                        type="button"

                        class="btn-change-service"

                        id="btnChangeService"

                        disabled

                    >

                        Cambiar

                    </button>



                </div>



                <input

                    type="hidden"

                    name="servicio"

                    id="servicio"

                    value=""

                >



                <div class="appointment-field">



                    <label for="psicologo">

                        Especialista

                    </label>



                    <select

                        name="psicologo"

                        id="psicologo"

                        class="form-select"

                        required

                    >



                        <option value="">

                            Selecciona un especialista

                        </option>



                        <?php foreach ($psicologos as $psicologo): ?>

                            <?php
                                $clvPsiOpcion = (string) ($psicologo['ClvPsi'] ?? '');
                                $seleccionadoPsi =
                                    strtoupper($clvPsiOpcion)
                                    === strtoupper(
                                        (string) ($psicologoPreseleccionado ?? '')
                                    );
                            ?>

                            <option

                                value="<?= htmlspecialchars(

                                    $clvPsiOpcion,

                                    ENT_QUOTES,

                                    'UTF-8'

                                ); ?>"

                                <?= $seleccionadoPsi ? 'selected' : ''; ?>

                            >

                                <?= htmlspecialchars(

                                    $psicologo['NombrePsicologo'],

                                    ENT_QUOTES,

                                    'UTF-8'

                                ); ?>



                                <?php if (

                                    !empty($psicologo['NombreCons'])

                                ): ?>

                                    (<?= htmlspecialchars(

                                        $psicologo['NombreCons'],

                                        ENT_QUOTES,

                                        'UTF-8'

                                    ); ?>)

                                <?php endif; ?>



                            </option>



                        <?php endforeach; ?>



                    </select>



                </div>



                <div class="appointment-field appointment-field--calendar">



                    <p class="appointment-field__label">

                        Fecha de la cita

                    </p>



                    <input

                        type="hidden"

                        name="fecha"

                        id="fecha"

                        value=""

                        required

                    >



                    <div

                        class="appointment-calendar"

                        id="appointmentCalendar"

                        aria-label="Calendario de fechas disponibles"

                    >



                        <div class="appointment-calendar-header">



                            <button

                                type="button"

                                class="appointment-calendar-nav-btn"

                                id="calendarPrevMonth"

                                aria-label="Mes anterior"

                                disabled

                            >

                                ‹

                            </button>



                            <span

                                class="appointment-calendar-header__title"

                                id="calendarHeader"

                            ></span>



                            <button

                                type="button"

                                class="appointment-calendar-nav-btn"

                                id="calendarNextMonth"

                                aria-label="Mes siguiente"

                                disabled

                            >

                                ›

                            </button>



                        </div>



                        <div

                            class="appointment-calendar-weekdays"

                            aria-hidden="true"

                        >



                            <span>Lun</span>

                            <span>Mar</span>

                            <span>Mié</span>

                            <span>Jue</span>

                            <span>Vie</span>

                            <span>Sáb</span>

                            <span>Dom</span>



                        </div>



                        <div

                            class="appointment-calendar-grid"

                            id="calendarGrid"

                            role="grid"

                            aria-labelledby="calendarHeader"

                        ></div>



                        <div

                            class="appointment-calendar-legend"

                            aria-label="Leyenda del calendario"

                        >



                            <span class="appointment-calendar-legend__item">

                                <span

                                    class="appointment-calendar-legend__swatch appointment-calendar-legend__swatch--available"

                                    aria-hidden="true"

                                ></span>

                                Disponible

                            </span>



                            <span class="appointment-calendar-legend__item">

                                <span

                                    class="appointment-calendar-legend__swatch appointment-calendar-legend__swatch--selected"

                                    aria-hidden="true"

                                ></span>

                                Seleccionado

                            </span>



                            <span class="appointment-calendar-legend__item">

                                <span

                                    class="appointment-calendar-legend__swatch appointment-calendar-legend__swatch--unavailable"

                                    aria-hidden="true"

                                ></span>

                                Sin disponibilidad

                            </span>



                        </div>



                        <div

                            id="calendarStatus"

                            class="appointment-loading-state mt-2"

                            aria-live="polite"

                        >

                            Selecciona un especialista y un servicio para

                            consultar las fechas disponibles.

                        </div>



                    </div>



                </div>



                <div class="appointment-field">



                    <p class="appointment-slots__label">

                        Horarios disponibles

                    </p>



                    <div

                        id="slotsStatus"

                        class="appointment-loading-state"

                        aria-live="polite"

                    ></div>



                    <div

                        id="slotsContainer"

                        class="appointment-time-slots mt-2"

                        role="group"

                        aria-label="Horarios disponibles para la fecha seleccionada"

                    ></div>



                    <input

                        type="hidden"

                        name="hora"

                        id="hora"

                        value=""

                        required

                    >



                </div>



                <div class="appointment-actions d-lg-none">

                    <button
                        type="submit"
                        class="btn-confirm-appointment"
                        disabled
                    >
                        Confirmar cita
                    </button>

                </div>



            </div>



            <aside class="appointment-summary">



                <div class="appointment-summary-card">



                    <h3 class="appointment-summary-card__title">

                        Resumen de tu cita

                    </h3>



                    <div class="appointment-specialist">



                        <img

                            src=""

                            alt=""

                            class="appointment-specialist-photo d-none"

                            id="summarySpecialistPhoto"

                        >



                        <span

                            class="appointment-specialist-photo appointment-specialist-photo--initials d-none"

                            id="summarySpecialistInitials"

                            aria-hidden="true"

                        ></span>



                        <div>



                            <p

                                class="appointment-specialist__name"

                                id="summarySpecialistName"

                            >

                                Selecciona un especialista

                            </p>



                            <p

                                class="appointment-specialist__meta"

                                id="summarySpecialistMeta"

                            ></p>



                            <p

                                class="appointment-specialist__clinic"

                                id="summarySpecialistClinic"

                            ></p>



                        </div>



                    </div>



                    <div class="appointment-detail-row">



                        <i

                            class="bi bi-calendar3 appointment-detail-row__icon"

                            aria-hidden="true"

                        ></i>



                        <div class="appointment-detail-row__content">



                            <span class="appointment-detail-row__label">

                                Fecha y hora

                            </span>



                            <p

                                class="appointment-detail-row__value"

                                id="summaryDateTime"

                            >

                                Selecciona fecha y horario

                            </p>



                            <p

                                class="appointment-detail-row__sub"

                                id="summaryDateTimeSub"

                            ></p>



                            <button

                                type="button"

                                class="btn-link-change-date"

                                id="btnChangeDate"

                            >

                                Cambiar la fecha

                            </button>



                        </div>



                    </div>



                    <div class="appointment-detail-row">



                        <i

                            class="bi bi-briefcase appointment-detail-row__icon"

                            aria-hidden="true"

                        ></i>



                        <div class="appointment-detail-row__content">



                            <span class="appointment-detail-row__label">

                                Servicio

                            </span>



                            <p

                                class="appointment-detail-row__value"

                                id="summaryService"

                            >

                                Sin servicio seleccionado

                            </p>



                            <p

                                class="appointment-detail-row__sub"

                                id="summaryServiceSub"

                            ></p>



                        </div>



                    </div>



                    <div class="appointment-detail-row">



                        <i

                            class="bi bi-geo-alt appointment-detail-row__icon"

                            aria-hidden="true"

                        ></i>



                        <div class="appointment-detail-row__content">



                            <span class="appointment-detail-row__label">

                                Ubicación

                            </span>



                            <p

                                class="appointment-detail-row__value"

                                id="summaryLocation"

                            >

                                La dirección del consultorio está pendiente

                                de configurar.

                            </p>



                            <p

                                class="appointment-detail-row__sub d-none"

                                id="summaryReference"

                            ></p>



                        </div>



                    </div>



                    <p class="appointment-attention mb-2">



                        <i class="bi bi-building me-1" aria-hidden="true"></i>



                        Atención presencial en el consultorio



                    </p>



                    <p class="appointment-payment mb-3">



                        <i class="bi bi-cash-coin me-1" aria-hidden="true"></i>



                        Pago directamente en el consultorio



                    </p>



                    <div class="appointment-policy" id="summaryPolicy">



                        Para conocer las condiciones de cancelación,

                        comunícate con el consultorio.



                    </div>



                    <div class="appointment-actions d-none d-lg-block">



                        <button

                            type="submit"

                            id="btnConfirmar"

                            class="btn-confirm-appointment"

                            disabled

                        >

                            Confirmar cita

                        </button>



                    </div>



                </div>



            </aside>



        </div>



    </form>



</section>



<div

    class="modal fade services-modal"

    id="servicesModal"

    tabindex="-1"

    aria-labelledby="servicesModalTitle"

    aria-hidden="true"

>



    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">



        <div class="modal-content">



            <div class="modal-header">



                <div>



                    <h2

                        class="modal-title h5 mb-0"

                        id="servicesModalTitle"

                    >

                        Selecciona el servicio

                    </h2>



                    <p class="modal-subtitle">

                        Servicios disponibles con el especialista seleccionado

                    </p>



                </div>



                <button

                    type="button"

                    class="btn-close"

                    data-bs-dismiss="modal"

                    aria-label="Cerrar"

                ></button>



            </div>



            <div

                class="modal-body"

                id="servicesModalList"

                role="listbox"

                aria-label="Lista de servicios"

            ></div>



        </div>



    </div>



</div>
