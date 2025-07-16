# Contributing to Laravel Google Pub/Sub

Thank you for considering contributing to Laravel Google Pub/Sub! We welcome contributions from the community and
are grateful for any help you can provide.

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check existing issues to avoid duplicates. When you create a bug report, include as
many details as possible:

- **Use a clear and descriptive title**
- **Describe the exact steps to reproduce the problem**
- **Provide specific examples to demonstrate the steps**
- **Describe the behavior you observed and what you expected**
- **Include your environment details** (PHP version, Laravel version, package version)

### Suggesting Features

Feature suggestions are welcome! Please create an issue and include:

- **A clear and descriptive title**
- **A detailed description of the proposed feature**
- **Why this enhancement would be useful**
- **Possible implementation approach** (if you have ideas)

### Code Contributions

#### Getting Started

1. Clone the repository:
   ```bash
   git clone https://github.com/shavonn/laravel-google-pubsub.git
   cd laravel-google-pubsub
   ```

   *Note: If you don't have write access to the repository, you'll need to fork it first and clone your fork instead:*
   ```bash
   git clone https://github.com/your-username/laravel-google-pubsub.git
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Create a new branch:
   ```bash
   git checkout -b feature/your-feature-name
   # or
   git checkout -b bugfix/issue-number
   ```

#### Development Process

- **Follow PSR-12 coding standards**
- **Write tests for new features** - aim for good test coverage
- **Update documentation** as needed
- **Keep commits focused** - one feature/fix per commit when possible
- **Write clear commit messages** following this format:
  ```
  Short summary (50 chars or less)
  
  More detailed explanation if needed. Wrap at 72 characters.
  Explain the problem this commit solves and why this approach.
  
  Fixes #123
  ```

#### Running Tests

Before submitting your changes, ensure all tests pass:

```bash
composer test
# or
./vendor/bin/phpunit
```

#### Submitting Pull Requests

1. Push your changes:
    - If you have write access: `git push origin your-branch-name`
    - If working from a fork: `git push origin your-branch-name` (this pushes to your fork)
2. Create a pull request:
    - Go to the [Laravel Google Pub/Sub repository](https://github.com/shavonn/laravel-google-pubsub)
    - Click "New pull request"
    - If working from a fork, click "compare across forks"
    - Select your branch and create the PR
3. Include a clear description of the changes:
    - What issue does this address?
    - What is the solution?
    - Any breaking changes?
    - Any new dependencies?
4. Link any related issues
5. Ensure all CI checks pass

### Documentation Contributions

Improvements to documentation are always welcome! This includes:

- Fixing typos or clarifying existing documentation
- Adding examples and use cases
- Improving inline code documentation
- Expanding the README

### Adding Examples

Practical examples help users understand how to use the package. When adding examples:

- Make them realistic and practical
- Include necessary context
- Comment code to explain what's happening
- Test that examples actually work

### Helping Others

You can contribute by:

- Answering questions in GitHub issues
- Reviewing pull requests
- Testing PRs and providing feedback
- Sharing your use cases

### Testing

We use PHPUnit for testing. Tests are located in the `tests/` directory.

- Write tests for any new functionality
- Ensure existing tests pass
- Aim for meaningful test coverage

## Communication

- **GitHub Issues** - For bug reports, feature requests, and general discussions
- **Pull Request comments** - For specific code-related discussions
- Be respectful and constructive in all interactions

## Questions?

If you have questions about contributing, feel free to open an issue with the "question" label.

Thank you for contributing to Laravel Google Pub/Sub! 🎉
