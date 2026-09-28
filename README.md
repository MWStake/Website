# MWStake Website

This repository manages the `mwstake` Canasta instance on `meta.wikiapiary.com`.
It also serves `dev.mwstake.org`.

## Join this GitOps repository

Use Canasta to join another existing instance host:

```sh
canasta gitops join -i mwstake -n <host-name> \
  --repo git@github.com:MWStake/Website.git \
  --key ~/.config/canasta/mwstake-gc.key \
  --ssh-key ~/.ssh/id_canasta_mwstake_deploy
```

The git-crypt key is provided by Mark Hershberger. Obtain and store it securely; do not commit it to this repository.
