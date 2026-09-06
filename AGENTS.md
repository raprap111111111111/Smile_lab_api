# Engineering Standards & Behavioral Rules: Production-Ready Business Systems

## Core Directive
Everything built, refactored, or touched in this workspace is **production-ready** for enterprise and business deployment in a professional setting. We do not use shortcuts, temporary hacks, or bypassed validations. Every component must be robust, secure, maintainable, and built for real-world operations.

---

## 1. Professional & Production-Ready Standards
- **Enterprise Grade**: Systems must be reliable, performant, and resilient against real-world business loads and failure modes.
- **Defensive Engineering**: Explicitly handle nullability, timeouts, network interruptions, validation failures, concurrency, and error states.
- **Graceful Error Handling**: Never expose raw system traces or unhandled exceptions to users. Provide clear, actionable feedback and logging.

## 2. Architecture & Code Quality
- **Separation of Concerns**:
  - **Flutter Client (`smile_lab_inv`)**: Adhere to clean layered architecture (Domain, Data/Repositories/Services, UI/Presentation, State Management). Keep UI decoupled from backend details.
  - **Backend API (`Smile_lab_api`)**: Follow clean Laravel/DDD conventions (Domain, DTOs, Mappers, Actions/Services, Models, Form Requests). Keep controllers thin.
- **Strict Typing & Static Analysis**:
  - Explicit typing, sound null safety, and clean linting in Dart.
  - Strict types (`declare(strict_types=1);`), explicit parameter/return types, and DTOs in PHP 8+.
  - Zero tolerance for avoidable linter errors or compilation warnings.
- **Maintainability**: Self-documenting, readable code following official guidelines (Effective Dart, PSR-12).

## 3. Security & Data Integrity
- **Role-Based Access Control (RBAC)**: Enforce authorization strictly on both the backend API (policies, gates, middleware) and frontend UI (conditional views, route guards).
- **Data Validation & Sanitization**: Never trust client input. Validate all incoming payloads with strict validation rules.
- **Secrets Management**: Never commit credentials, tokens, or production secrets into source control.

## 4. UI/UX & Design Excellence
- **Polished Enterprise UI**: Consistent design tokens, typography, responsive layouts (mobile, tablet, desktop), and clear visual hierarchy.
- **No Layout Regressions**: Zero tolerance for layout overflows (`RenderFlex`), unconstrained viewports, or jarring UI shifts.
- **State Feedback**: Provide comprehensive loading states, skeletons, and meaningful empty/error states.

## 5. Verification & Testing
- Always verify changes via static analysis (`dart analyze`, Laravel checks) and runtime tests before concluding work.
