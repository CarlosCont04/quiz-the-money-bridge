export {};
type Answer = 'A' | 'B' | 'C';
interface QuizResult { score: number; maxScore: number; key: 'red' | 'yellow' | 'green'; color: string; title: string; description: string; }
interface ApiResponse { csrfToken?: string; message?: string; result?: QuizResult; }

function element<T extends HTMLElement>(selector: string): T {
  const found = document.querySelector<T>(selector);
  if (!found) throw new Error(`Missing quiz element: ${selector}`);
  return found;
}

const form = element<HTMLFormElement>('#quiz-form');
const steps = Array.from(document.querySelectorAll<HTMLFieldSetElement>('.question-step'));
const contact = element<HTMLFieldSetElement>('#contact-step');
const back = element<HTMLButtonElement>('#back-button');
const next = element<HTMLButtonElement>('#next-button');
const formStatus = element<HTMLParagraphElement>('#form-status');
const progress = element<HTMLProgressElement>('#quiz-progress');
const resultDialog = element<HTMLDialogElement>('#result-dialog');
const privacyDialog = element<HTMLDialogElement>('#privacy-dialog');
const answers: (Answer | null)[] = steps.map(() => null);
const api = form.dataset.api!;
let currentStep = 0;
let submitting = false;
let requestId = crypto.randomUUID();
let completedResult: QuizResult | null = null;
let previousPayload = '';

function setStatus(message = '', loading = false) {
  formStatus.textContent = message;
  formStatus.dataset.state = loading ? 'loading' : 'error';
}

function updateProgress() {
  const answered = answers.filter(Boolean).length;
  progress.value = answered;
  progress.textContent = `${answered} de ${steps.length}`;
  element('#progress-label').textContent = `${Math.round(answered / steps.length * 100)}% completado`;
}

function showStep(focus = true) {
  steps.forEach((step, index) => {
    step.hidden = index !== currentStep;
    step.disabled = index !== currentStep;
    step.querySelectorAll<HTMLInputElement>('input').forEach((input) => { input.disabled = index !== currentStep; });
  });
  contact.hidden = currentStep !== steps.length;
  contact.disabled = currentStep !== steps.length;
  back.disabled = currentStep === 0;
  element('#step-label').textContent = currentStep === steps.length ? 'TUS DATOS · ÚLTIMO PASO' : `PREGUNTA ${String(currentStep + 1).padStart(2, '0')} DE ${steps.length}`;
  element('#next-label').textContent = currentStep === steps.length ? 'Ver mi resultado' : 'Siguiente';
  updateProgress();
  setStatus();
  if (focus) {
    const active = currentStep === steps.length ? contact : steps[currentStep];
    active.querySelector<HTMLElement>('.question-title')?.focus({ preventScroll: true });
    const card = element('#quiz-card');
    if (card.getBoundingClientRect().top < 0) card.scrollIntoView({ block: 'start', behavior: 'instant' });
  }
}

async function apiRequest(path: string, options: RequestInit = {}): Promise<ApiResponse> {
  const response = await fetch(`${api}${path}`, { ...options, credentials: 'same-origin', signal: AbortSignal.timeout(15000), headers: { Accept: 'application/json', ...options.headers } });
  let data: ApiResponse;
  try { data = await response.json(); } catch { throw new Error('El servicio no está disponible. Intenta de nuevo en un momento; tus respuestas siguen aquí.'); }
  if (!response.ok) throw new Error(data.message || 'No pudimos guardar tu quiz. Intenta de nuevo.');
  return data;
}

function showResult(result: QuizResult) {
  resultDialog.dataset.result = result.key;
  element('#result-title').textContent = result.title;
  element('#result-score').textContent = String(result.score);
  element('#result-color').textContent = `Semáforo ${result.color.toLowerCase()}`;
  element('#result-description').textContent = result.description;
  const name = element<HTMLInputElement>('#full-name').value.trim();
  element('#result-personal').textContent = `${name}, conocer tu punto de partida ya es avanzar.`;
  resultDialog.showModal();
  element('#result-title').focus();
}

form.addEventListener('change', (event) => {
  const target = event.target;
  if (!(target instanceof HTMLInputElement) || target.type !== 'radio') return;
  const questionIndex = Number(target.name.replace('question-', '')) - 1;
  answers[questionIndex] = target.value as Answer;
  updateProgress();
});

back.addEventListener('click', () => { if (currentStep > 0 && !submitting) { currentStep--; showStep(); } });

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  if (submitting) return;
  if (currentStep < steps.length) {
    if (!answers[currentStep]) { setStatus('Selecciona una respuesta para continuar.'); return; }
    currentStep++;
    showStep();
    return;
  }
  if (!form.reportValidity()) return;
  const nameInput = element<HTMLInputElement>('#full-name');
  const emailInput = element<HTMLInputElement>('#email');
  if (nameInput.value.trim().length < 2) { setStatus('Escribe tu nombre con al menos dos caracteres.'); nameInput.focus(); return; }
  submitting = true;
  next.disabled = back.disabled = true;
  form.setAttribute('aria-busy', 'true');
  setStatus('Guardando tus respuestas y preparando tu resultado…', true);
  const values = { name: nameInput.value.trim(), email: emailInput.value.trim(), consent: element<HTMLInputElement>('[name="consent"]').checked, website: element<HTMLInputElement>('#website').value, answers: answers.map((answer, index) => ({ questionId: index + 1, answer })) };
  const serialized = JSON.stringify(values);
  if (previousPayload && previousPayload !== serialized) requestId = crypto.randomUUID();
  previousPayload = serialized;
  const payload = { requestId, ...values };
  contact.disabled = true;
  try {
    const session = await apiRequest('session.php');
    if (!session.csrfToken) throw new Error('No pudimos iniciar una sesión segura. Intenta de nuevo.');
    const response = await apiRequest('submit.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': session.csrfToken }, body: JSON.stringify(payload) });
    if (!response.result) throw new Error('No pudimos consultar tu resultado. Intenta de nuevo.');
    completedResult = response.result;
    form.hidden = true;
    element('#quiz-complete').hidden = false;
    element('#reopen-result').focus({ preventScroll: true });
    showResult(response.result);
  } catch (error) {
    setStatus(error instanceof Error && error.name !== 'TimeoutError' && error.name !== 'TypeError' ? error.message : 'No pudimos conectar con el servicio. Revisa tu conexión e intenta de nuevo; conservamos tus respuestas en esta página.');
  } finally {
    submitting = false;
    contact.disabled = false;
    next.disabled = back.disabled = false;
    form.removeAttribute('aria-busy');
  }
});

document.querySelectorAll<HTMLButtonElement>('[data-close-dialog]').forEach((button) => button.addEventListener('click', () => button.closest('dialog')?.close()));
document.querySelectorAll<HTMLButtonElement>('[data-open-privacy]').forEach((button) => button.addEventListener('click', () => { privacyDialog.showModal(); element('#privacy-title').focus(); }));
element('#reopen-result').addEventListener('click', () => { if (completedResult) showResult(completedResult); });
element('#restart-quiz').addEventListener('click', () => {
  form.reset();
  answers.fill(null);
  requestId = crypto.randomUUID();
  previousPayload = '';
  completedResult = null;
  currentStep = 0;
  element('#quiz-complete').hidden = true;
  form.hidden = false;
  showStep();
});
form.hidden = false;
showStep(false);
